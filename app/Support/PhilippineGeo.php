<?php

namespace App\Support;

/**
 * Crosswalk between Pancake shipping-address names and GADM level-2 feature ids.
 *
 * The RTS heat map shades polygons from public/ph_geojson.json, whose features are
 * keyed by `GID_2` (city/municipality) and `GID_1` (province). Pancake stores free
 * text names on shipping_addresses, so public/pancake_to_gadm.json provides the
 * lookup. Both files carry GADM *names* with the spaces stripped ("LasPiñas",
 * "MetropolitanManila"), so matching on names is unreliable — everything here
 * resolves to an id, and display labels come from the Pancake names instead.
 *
 * Pancake's own names are messy too: hyphenated ("Davao-del-sur"), sometimes
 * prefixed with their province ("Agusan-del-norte-buenavista"), sometimes carrying
 * a "-city" suffix the crosswalk lacks. The resolver below works through those
 * variants in order, which lifts coverage from ~65% of rows to ~97%.
 *
 * Provinces come from public/ph_provinces.json — the authoritative Pancake list,
 * matching shipping_addresses on both `province_id` and `province_name`. It is what
 * supplies the readable label (`name_en`) and the island group (`region_type`);
 * neither can be derived from the GADM data.
 */
class PhilippineGeo
{
    /**
     * Districts of the City of Manila. Pancake records these as if they were
     * municipalities; GADM has no level-2 feature for them, only Manila itself.
     */
    private const MANILA_DISTRICTS = [
        'binondo', 'ermita', 'intramuros', 'malate', 'paco', 'pandacan',
        'port area', 'quiapo', 'sampaloc', 'san andres', 'san miguel',
        'san nicolas', 'santa ana', 'santa cruz', 'santa mesa', 'tondo',
    ];

    /**
     * Pairs the crosswalk leaves unmapped, keyed by "province|district" after
     * normalisation. Mostly Metro Manila quirks plus municipalities that were
     * renamed or created after the GADM boundaries were drawn.
     */
    private const OVERRIDES = [
        'manila|caloocan' => 'PHL.47.1_1',
        'manila|north caloocan' => 'PHL.47.1_1',
        'manila|south caloocan' => 'PHL.47.1_1',
        'manila|kalookan' => 'PHL.47.1_1',
        'laguna|binan' => 'PHL.35.4_1',
        'isabela|isabela' => 'PHL.31.16_1',
        // Renamed after GADM: Amai Manabilang was Bumbaran.
        'lanao del sur|amai manabilang' => 'PHL.36.9_1',
    ];

    /** @var array<string, string>|null "province|district" => GID_2 */
    private static ?array $districts = null;

    /** @var array<string, string>|null "province" => GID_1 */
    private static ?array $provinces = null;

    /** @var array<string, array<string, mixed>>|null normalised name => province record */
    private static ?array $registryByName = null;

    /** @var array<string, array<string, mixed>>|null Pancake province id => province record */
    private static ?array $registryById = null;

    /**
     * GID_2 of the city/municipality, or null when the pair cannot be placed on
     * the map (blank names, or a municipality GADM does not know about).
     */
    public static function districtGid(?string $province, ?string $district): ?string
    {
        self::load();

        $p = self::provinceKey($province);
        $d = self::normalize($district);

        if ($p === '' || $d === '') {
            return null;
        }

        // Strip either spelling of the province: the crosswalk key ('manila') or
        // the raw one Pancake records ('metro manila').
        $variants = self::districtVariants([$p, self::normalize($province)], $d);

        foreach ($variants as $variant) {
            if (isset(self::OVERRIDES[$p.'|'.$variant])) {
                return self::OVERRIDES[$p.'|'.$variant];
            }

            if (isset(self::$districts[$p.'|'.$variant])) {
                return self::$districts[$p.'|'.$variant];
            }
        }

        if ($p === 'manila' && self::isManilaDistrict($variants)) {
            return 'PHL.47.6_1'; // City of Manila
        }

        return null;
    }

    /**
     * Manila's districts sometimes carry a trailing qualifier ("Tondo I/II"), so
     * a prefix match is enough once we know we are inside Metro Manila.
     *
     * @param  list<string>  $variants
     */
    private static function isManilaDistrict(array $variants): bool
    {
        foreach ($variants as $variant) {
            foreach (self::MANILA_DISTRICTS as $district) {
                if ($variant === $district || str_starts_with($variant, $district.' ')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The province record from Pancake's own list: id, name, name_en, region and
     * the GADM id of its polygon. `province_id` is an exact key when the caller has
     * one; the name is matched leniently for the rows that do not.
     *
     * @return array{id: string, name: string, name_en: string, region: string, gid: string|null}|null
     */
    public static function province(?string $name, ?string $id = null): ?array
    {
        self::load();

        if ($id !== null && isset(self::$registryById[$id])) {
            return self::$registryById[$id];
        }

        return self::$registryByName[self::provinceKey($name)] ?? null;
    }

    /** Island group — "Metro Manila", "North Luzon", "South Luzon", "Visayas", "Mindanao". */
    public static function region(?string $name, ?string $id = null): ?string
    {
        return self::province($name, $id)['region'] ?? null;
    }

    /**
     * Readable province name. Pancake stores "Davao-del-sur"; its own list spells
     * that "Davao del sur", which beats title-casing the hyphenated form.
     */
    public static function provinceLabel(?string $name, ?string $id = null): ?string
    {
        return self::province($name, $id)['name_en'] ?? null;
    }

    /**
     * Every province polygon in an island group. A region has no boundary of its
     * own in GADM, so the map shades each of its provinces with the region's value.
     *
     * @return list<string>
     */
    public static function regionGids(?string $region): array
    {
        self::load();

        $gids = [];

        foreach (self::$registryByName ?? [] as $province) {
            if ($province['region'] === $region && $province['gid'] !== null) {
                $gids[] = $province['gid'];
            }
        }

        return array_values(array_unique($gids));
    }

    /**
     * The island groups, in the order they read on a map of the country.
     *
     * @return list<string>
     */
    public static function regions(): array
    {
        return ['Metro Manila', 'North Luzon', 'South Luzon', 'Visayas', 'Mindanao'];
    }

    /**
     * Label for a province polygon. Usually one province, but GADM predates some
     * provincial splits — Davao Occidental left Davao del Sur in 2013 and the two
     * still share PHL.28_1 — so a shared polygon names every province in it rather
     * than crediting one with the other's orders.
     */
    public static function provinceLabelForGid(?string $gid): ?string
    {
        self::load();

        if ($gid === null) {
            return null;
        }

        $names = [];

        foreach (self::$registryByName ?? [] as $province) {
            if ($province['gid'] === $gid) {
                $names[] = $province['name_en'];
            }
        }

        return $names === [] ? null : implode(' / ', $names);
    }

    /** GID_1 of the province, or null when it cannot be placed on the map. */
    public static function provinceGid(?string $province, ?string $id = null): ?string
    {
        self::load();

        return self::province($province, $id)['gid']
            ?? self::$provinces[self::provinceKey($province)]
            ?? null;
    }

    /**
     * Spellings to try, in order: as recorded, with any of the province prefixes
     * Pancake prepends stripped, and with a "-city" suffix added or removed.
     *
     * @param  list<string>  $provinces
     * @return list<string>
     */
    private static function districtVariants(array $provinces, string $district): array
    {
        $bases = [$district];

        foreach (array_unique($provinces) as $province) {
            if ($province !== '' && str_starts_with($district, $province.' ')) {
                $bases[] = substr($district, strlen($province) + 1);
            }
        }

        $variants = [];

        foreach ($bases as $base) {
            $variants[] = $base;
            $variants[] = str_ends_with($base, ' city')
                ? substr($base, 0, -5)
                : $base.' city';
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * Lowercase, de-hyphenate and strip punctuation/accents so "Davao-del-sur",
     * "DAVAO DEL SUR" and "Gen.-mariano-alvarez" all reduce to a common form.
     */
    private static function normalize(?string $name): string
    {
        $value = mb_strtolower(trim((string) $name));

        $value = strtr($value, [
            'ñ' => 'n', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);

        $value = str_replace(['-', '_', '.', ',', '/'], ' ', $value);
        $value = (string) preg_replace('/\s+/', ' ', $value);
        $value = trim($value);

        // Abbreviations Pancake uses but the crosswalk spells out.
        return (string) preg_replace(
            ['/\bgen\b/', '/\bsto\b/', '/\bsta\b/'],
            ['general', 'santo', 'santa'],
            $value
        );
    }

    /** Metro Manila is "Manila" in the crosswalk but "Metro-manila" in Pancake. */
    private static function provinceKey(?string $province): string
    {
        $value = self::normalize($province);

        return $value === 'metro manila' || $value === 'metropolitan manila'
            ? 'manila'
            : $value;
    }

    private static function load(): void
    {
        if (self::$districts !== null) {
            return;
        }

        self::$districts = [];
        self::$provinces = [];

        $path = public_path('pancake_to_gadm.json');
        $rows = is_readable($path)
            ? json_decode((string) file_get_contents($path), true)
            : null;

        if (! is_array($rows)) {
            return;
        }

        $provinceTally = [];

        foreach ($rows as $row) {
            $gid = $row['gadm_gid'] ?? null;

            if (! is_string($gid) || $gid === '') {
                continue;
            }

            $province = self::provinceKey($row['pancake_province_name'] ?? null);
            $district = self::normalize($row['pancake_district_name'] ?? null);

            if ($province === '' || $district === '') {
                continue;
            }

            self::$districts[$province.'|'.$district] = $gid;

            // "PHL.47.6_1" (city) => "PHL.47_1" (its province).
            if (preg_match('/^(\w+\.\d+)\.\d+_\d+$/', $gid, $m)) {
                $provinceTally[$province][$m[1].'_1'] ??= 0;
                $provinceTally[$province][$m[1].'_1']++;
            }
        }

        // A handful of provinces straddle two GADM ids; take the dominant one.
        foreach ($provinceTally as $province => $tally) {
            arsort($tally);
            self::$provinces[$province] = array_key_first($tally);
        }

        self::loadRegistry();
    }

    private static function loadRegistry(): void
    {
        self::$registryByName = [];
        self::$registryById = [];

        $path = public_path('ph_provinces.json');
        $rows = is_readable($path)
            ? json_decode((string) file_get_contents($path), true)
            : null;

        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $key = self::provinceKey($row['name'] ?? null);

            if ($key === '') {
                continue;
            }

            $record = [
                'id' => (string) ($row['id'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'name_en' => (string) ($row['name_en'] ?? $row['name'] ?? ''),
                'region' => (string) ($row['region_type'] ?? ''),
                'gid' => self::$provinces[$key] ?? null,
            ];

            self::$registryByName[$key] = $record;

            if ($record['id'] !== '') {
                self::$registryById[$record['id']] = $record;
            }
        }
    }
}
