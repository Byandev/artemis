<?php

namespace Modules\Pancake\Support;

use Illuminate\Support\Collection;
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

        $matchedProvince = $provinceKey === ''
            ? null
            : $this->best(Province::where('country_code', $countryCode)->get(), $provinceKey);

        $matchedDistrict = null;
        // Districts that tie on the city name — Pancake lists a few twice
        // ("TONDO I/II"); the barangay then decides between them.
        $tiedDistricts = collect();

        if (filled($city)) {
            $cityKey = $this->normalize($city);

            if ($matchedProvince) {
                $leaders = $this->leaders($matchedProvince->districts()->get(), $cityKey);
                $matchedDistrict = $leaders->count() === 1 ? $leaders->first() : null;
                $tiedDistricts = $leaders->count() > 1 ? $leaders : collect();
            }

            // No province, or the city is not in it: a city name that exists in
            // only one province settles both.
            if (! $matchedDistrict && $tiedDistricts->isEmpty()) {
                $matchedDistrict = $this->best(
                    District::whereIn('province_id', Province::where('country_code', $countryCode)->select('id'))->get(),
                    $cityKey,
                );

                if ($matchedDistrict) {
                    $matchedProvince = $matchedDistrict->province;
                }
            }
        }

        $matchedCommune = null;

        if (filled($barangay)) {
            $barangayKey = $this->normalize($barangay);

            if ($matchedDistrict) {
                $matchedCommune = $this->best($matchedDistrict->communes()->get(), $barangayKey);
            } elseif ($matchedProvince) {
                // The city was missing or ambiguous: a barangay found in only one
                // of the candidate districts names the district.
                $communes = $tiedDistricts->isNotEmpty()
                    ? Commune::whereIn('district_id', $tiedDistricts->pluck('id'))->get()
                    : $matchedProvince->communes()->get();

                $matchedCommune = $this->best($communes, $barangayKey);
                $matchedDistrict = $matchedCommune?->district;
            }
        }

        $found = array_filter([$matchedProvince, $matchedDistrict, $matchedCommune]);

        return [
            'province' => $matchedProvince,
            'district' => $matchedDistrict,
            'commune' => $matchedCommune,
            'status' => match (count($found)) {
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
     * The one candidate that matches best, or null when nothing clears the
     * threshold or two different places tie for first.
     *
     * @template T of Province|District|Commune
     *
     * @param  Collection<int, T>  $candidates
     * @return T|null
     */
    private function best(Collection $candidates, string $key)
    {
        $leaders = $this->leaders($candidates, $key);

        return $leaders->count() === 1 ? $leaders->first() : null;
    }

    /**
     * Every candidate sharing the top score, as long as it clears the threshold.
     *
     * @template T of Province|District|Commune
     *
     * @param  Collection<int, T>  $candidates
     * @return Collection<int, T>
     */
    private function leaders(Collection $candidates, string $key): Collection
    {
        if ($key === '' || $candidates->isEmpty()) {
            return collect();
        }

        $scored = $candidates
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

        foreach (array_unique(array_filter([$place->name, $place->name_en])) as $name) {
            $candidate = $this->normalize($name);

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
