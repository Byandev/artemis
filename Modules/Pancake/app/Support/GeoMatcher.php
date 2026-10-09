<?php

namespace Modules\Pancake\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pancake\Models\Commune;
use Modules\Pancake\Models\District;
use Modules\Pancake\Models\Province;

/**
 * Turns free-text province / city / barangay names into Pancake's location ids,
 * using our copy of Pancake's list (`pancake:sync-geo`).
 *
 * Pancake's names are messy — "Agusan-del-norte", "Batangas-san-juan" (with
 * name_en "san juan"), "Poblacion vii", and a few duplicates — so both sides
 * are normalised before comparing, and a level that cannot be told apart is
 * left empty rather than guessed. Each level falls back on the one below it:
 * a barangay that only exists in one district of the province names that
 * district, and a city that only exists in one province names the province.
 *
 * Every row carries its names pre-normalised (search_key / search_key_en), so
 * an exact match is a single indexed lookup; only when that finds nothing are
 * the candidates loaded and scored for a near match. Changing normalize()
 * means running refreshSearchKeys() — `pancake:sync-geo` does.
 */
class GeoMatcher
{
    /** Below this similarity (0–100) a name is not a match. */
    public const THRESHOLD = 85;

    private const PROVINCE_ALIASES = [
        'ncr' => 'metro manila',
        'national capital region' => 'metro manila',
        'manila' => 'metro manila',
        'mm' => 'metro manila',
        'samar' => 'western samar',
        'compostela valley' => 'davao de oro',
        'north cotabato' => 'cotabato',
        'mount province' => 'mountain province',
    ];

    private const ABBREVIATIONS = [
        'sta' => 'santa',
        'sto' => 'santo',
        'gen' => 'general',
        'pres' => 'president',
        'mt' => 'mount',
    ];

    private const NOISE_WORDS = ['city', 'of', 'municipality', 'province', 'brgy', 'bgy', 'brg', 'barangay'];

    private const ROMAN = [
        'i' => '1', 'ii' => '2', 'iii' => '3', 'iv' => '4', 'v' => '5', 'vi' => '6', 'vii' => '7',
        'viii' => '8', 'ix' => '9', 'x' => '10', 'xi' => '11', 'xii' => '12', 'xiii' => '13',
        'xiv' => '14', 'xv' => '15', 'xvi' => '16', 'xvii' => '17', 'xviii' => '18', 'xix' => '19', 'xx' => '20',
    ];

    /**
     * @return array{province: ?Province, district: ?District, commune: ?Commune, status: 'complete'|'partial'|'not_found'}
     */
    public function match(?string $province, ?string $city, ?string $barangay, int $countryCode = 63): array
    {
        $provinceKey = $this->normalize((string) $province);
        $provinceKey = self::PROVINCE_ALIASES[$provinceKey] ?? $provinceKey;

        $matchedProvince = $this->best(Province::where('country_code', $countryCode), $provinceKey);

        $matchedDistrict = null;
        // Districts that tie on the city name — Pancake lists a few twice
        // ("TONDO I/II"); the barangay then decides between them.
        $tiedDistricts = collect();

        if (filled($city)) {
            $cityKey = $this->normalize($city);

            if ($matchedProvince) {
                $leaders = $this->leaders(District::where('province_id', $matchedProvince->id), $cityKey);
                $matchedDistrict = $leaders->count() === 1 ? $leaders->first() : null;
                $tiedDistricts = $leaders->count() > 1 ? $leaders : collect();
            }

            // No province, or the city is not in it: a city name that exists in
            // only one province settles both.
            if (! $matchedDistrict && $tiedDistricts->isEmpty()) {
                $matchedDistrict = $this->best(
                    District::whereIn('province_id', Province::where('country_code', $countryCode)->select('id')),
                    $cityKey,
                );

                if ($matchedDistrict) {
                    $matchedProvince = Province::find($matchedDistrict->province_id);
                }
            }
        }

        $matchedCommune = null;

        if (filled($barangay)) {
            if ($matchedDistrict) {
                $matchedCommune = $this->communeIn($matchedDistrict, $barangay);
            } elseif ($matchedProvince) {
                // The city was missing or ambiguous: a barangay found in only one
                // of the candidate districts names the district.
                $communes = $tiedDistricts->isNotEmpty()
                    ? Commune::whereIn('district_id', $tiedDistricts->pluck('id'))
                    : Commune::where('province_id', $matchedProvince->id);

                $matchedCommune = $this->best($communes, $this->normalize($barangay));
                $matchedDistrict = $matchedCommune ? District::find($matchedCommune->district_id) : null;
            }
        }

        return $this->result($matchedProvince, $matchedDistrict, $matchedCommune);
    }

    /** The district in a province that a typed city name means, if exactly one. */
    public function districtIn(Province $province, ?string $city): ?District
    {
        return $this->best(District::where('province_id', $province->id), $this->normalize((string) $city));
    }

    /** The commune in a district that a typed barangay name means, if exactly one. */
    public function communeIn(District $district, ?string $barangay): ?Commune
    {
        return $this->best(Commune::where('district_id', $district->id), $this->normalize((string) $barangay));
    }

    /**
     * @return array{province: ?Province, district: ?District, commune: ?Commune, status: 'complete'|'partial'|'not_found'}
     */
    public function result(?Province $province, ?District $district, ?Commune $commune): array
    {
        return [
            'province' => $province,
            'district' => $district,
            'commune' => $commune,
            'status' => match (count(array_filter([$province, $district, $commune]))) {
                3 => 'complete',
                0 => 'not_found',
                default => 'partial',
            },
        ];
    }

    public function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        $words = collect(explode(' ', trim((string) $value)))
            ->filter(fn (string $w) => $w !== '' && ! in_array($w, self::NOISE_WORDS, true))
            ->map(fn (string $w) => self::ABBREVIATIONS[$w] ?? self::ROMAN[$w] ?? $w);

        return $words->implode(' ');
    }

    /**
     * Recompute every row's search keys from its names. One UPDATE per
     * thousand rows, so the whole list (≈45k) takes a few seconds.
     */
    public function refreshSearchKeys(): void
    {
        foreach (['pancake_provinces', 'pancake_districts', 'pancake_communes'] as $table) {
            DB::table($table)->select('id', 'name', 'name_en')->orderBy('id')->chunk(1000, function ($rows) use ($table) {
                $ids = [];
                $keyCases = $enCases = '';
                $keyBindings = $enBindings = [];

                foreach ($rows as $row) {
                    $ids[] = $row->id;
                    $keyCases .= ' WHEN ? THEN ?';
                    $enCases .= ' WHEN ? THEN ?';
                    array_push($keyBindings, $row->id, $this->normalize((string) $row->name));
                    array_push($enBindings, $row->id, $row->name_en === null ? null : $this->normalize($row->name_en));
                }

                DB::update(
                    "UPDATE {$table} SET search_key = CASE id{$keyCases} END, search_key_en = CASE id{$enCases} END"
                    .' WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')',
                    [...$keyBindings, ...$enBindings, ...$ids],
                );
            });
        }
    }

    /**
     * The one candidate that matches best, or null when nothing clears the
     * threshold or two different places tie for first.
     *
     * @template T of Province|District|Commune
     *
     * @param  Builder<T>  $candidates
     * @return T|null
     */
    private function best(Builder $candidates, string $key)
    {
        $leaders = $this->leaders($candidates, $key);

        return $leaders->count() === 1 ? $leaders->first() : null;
    }

    /**
     * Every candidate sharing the top score, as long as it clears the threshold.
     * Exact matches come straight from the index; only without one are the
     * candidates loaded and scored.
     *
     * @template T of Province|District|Commune
     *
     * @param  Builder<T>  $candidates
     * @return Collection<int, T>
     */
    private function leaders(Builder $candidates, string $key): Collection
    {
        if ($key === '') {
            return collect();
        }

        $exact = (clone $candidates)
            ->where(fn (Builder $q) => $q->where('search_key', $key)->orWhere('search_key_en', $key))
            ->get();

        if ($exact->isNotEmpty()) {
            return $exact;
        }

        $scored = $candidates->get()
            ->map(fn ($c) => ['place' => $c, 'score' => $this->score($c, $key)])
            ->filter(fn (array $s) => $s['score'] >= self::THRESHOLD);

        $top = $scored->max('score');

        return $scored
            ->filter(fn (array $s) => $s['score'] === $top)
            ->pluck('place')
            ->values();
    }

    private function score(Province|District|Commune $place, string $key): float
    {
        $best = 0.0;

        // Falls back to normalising on the spot for a row synced before the
        // search keys existed.
        $candidates = array_unique(array_filter([
            $place->search_key ?? $this->normalize((string) $place->name),
            $place->search_key_en ?? ($place->name_en === null ? null : $this->normalize($place->name_en)),
        ]));

        foreach ($candidates as $candidate) {
            if ($candidate === $key) {
                return 100.0;
            }

            // "Batangas-san-juan" against "san juan": the place's own name with
            // its province prefixed for uniqueness.
            if (strlen($key) >= 5 && str_ends_with($candidate, ' '.$key)) {
                $best = max($best, 95.0);

                continue;
            }

            // "TONDO I/II" against "tondo": the typed name plus a qualifier.
            // Below the suffix rule and an exact match, so those still win.
            if (strlen($key) >= 5 && str_starts_with($candidate, $key.' ')) {
                $best = max($best, 90.0);

                continue;
            }

            similar_text($candidate, $key, $percent);
            $best = max($best, round($percent, 1));
        }

        return $best;
    }
}
