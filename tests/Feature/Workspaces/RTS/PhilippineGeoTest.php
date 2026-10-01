<?php

use App\Support\PhilippineGeo;

/**
 * Pancake writes place names as free text, so the crosswalk to GADM has to cope
 * with hyphenation, province prefixes, "-city" suffixes and Metro Manila's own
 * naming. These are the shapes actually present in shipping_addresses.
 */
it('resolves a plainly spelled municipality', function () {
    expect(PhilippineGeo::districtGid('Abra', 'Bangued'))->toBe('PHL.1.1_1');
});

it('ignores hyphenation and case', function () {
    expect(PhilippineGeo::districtGid('DAVAO-DEL-SUR', 'Davao-city'))
        ->toBe(PhilippineGeo::districtGid('Davao del Sur', 'davao city'));
});

it('strips the province prefix Pancake prepends to ambiguous municipalities', function () {
    expect(PhilippineGeo::districtGid('Agusan-del-sur', 'Agusan-del-sur-san-francisco'))
        ->toBe(PhilippineGeo::districtGid('Agusan-del-sur', 'San-francisco'));
});

it('matches with or without a city suffix', function () {
    expect(PhilippineGeo::districtGid('Laguna', 'Binan-city'))
        ->toBe(PhilippineGeo::districtGid('Laguna', 'Binan'));
});

it('expands abbreviated saints and generals', function () {
    expect(PhilippineGeo::districtGid('Cavite', 'Gen.-mariano-alvarez'))->not->toBeNull();
});

it('maps Metro Manila to the crosswalk spelling', function () {
    expect(PhilippineGeo::districtGid('Metro-manila', 'Makati'))->toBe('PHL.47.3_1');
});

it('folds the districts of Manila into the city itself', function () {
    // GADM has no level-2 feature for Ermita or Tondo — both are Manila.
    expect(PhilippineGeo::districtGid('Metro-manila', 'Ermita'))->toBe('PHL.47.6_1')
        ->and(PhilippineGeo::districtGid('Metro-manila', 'TONDO I/II'))->toBe('PHL.47.6_1')
        ->and(PhilippineGeo::districtGid('Metro-manila', 'Metro-manila-sampaloc'))->toBe('PHL.47.6_1');
});

it('sends both Caloocan halves to one polygon', function () {
    expect(PhilippineGeo::districtGid('Metro-manila', 'North-caloocan'))
        ->toBe(PhilippineGeo::districtGid('Metro-manila', 'CALOOCAN'));
});

it('returns null rather than guessing when the name is blank or unknown', function () {
    expect(PhilippineGeo::districtGid('Abra', null))->toBeNull()
        ->and(PhilippineGeo::districtGid(null, 'Bangued'))->toBeNull()
        ->and(PhilippineGeo::districtGid('Abra', 'Nowhere At All'))->toBeNull();
});

it('resolves provinces to their GADM id', function () {
    expect(PhilippineGeo::provinceGid('Metro-manila'))->toBe('PHL.47_1')
        ->and(PhilippineGeo::provinceGid('Davao-del-sur'))->toBe(PhilippineGeo::provinceGid('DAVAO DEL SUR'))
        ->and(PhilippineGeo::provinceGid('Nowhere'))->toBeNull();
});

it('resolves district ids that exist in the map boundaries', function () {
    $geo = json_decode((string) file_get_contents(public_path('ph_geojson.json')), true);
    $gids = array_column(array_column($geo['features'], 'properties'), 'GID_2');

    foreach ([['Abra', 'Bangued'], ['Metro-manila', 'Makati'], ['Cebu', 'Lapu-lapu-city']] as [$p, $d]) {
        expect($gids)->toContain(PhilippineGeo::districtGid($p, $d));
    }
});

/*
 * Pancake's own province list (public/ph_provinces.json) supplies the readable
 * label and the island group — neither is derivable from the GADM boundaries.
 */

it('resolves a province by name or by Pancake id', function () {
    expect(PhilippineGeo::province('Davao-del-sur')['id'])->toBe('63_738')
        ->and(PhilippineGeo::province(null, '63_738')['name_en'])->toBe('Davao del sur');
});

it('reads the province label off the list rather than title casing', function () {
    expect(PhilippineGeo::provinceLabel('Davao-del-norte'))->toBe('Davao del norte')
        ->and(PhilippineGeo::provinceLabel('Metro-manila'))->toBe('Manila');
});

it('places every province in an island group', function () {
    $list = json_decode((string) file_get_contents(public_path('ph_provinces.json')), true);

    foreach ($list as $province) {
        expect(PhilippineGeo::region($province['name']))
            ->toBeIn(PhilippineGeo::regions());
    }
});

it('gives every province a polygon on the map', function () {
    $list = json_decode((string) file_get_contents(public_path('ph_provinces.json')), true);

    foreach ($list as $province) {
        expect(PhilippineGeo::provinceGid($province['name']))->not->toBeNull();
    }
});

it('expands a region into the polygons it covers', function () {
    expect(PhilippineGeo::regionGids('Metro Manila'))->toBe(['PHL.47_1'])
        ->and(PhilippineGeo::regionGids('Visayas'))->toHaveCount(16)
        ->and(PhilippineGeo::regionGids('Nowhere'))->toBe([]);
});

it('names a polygon after every province sharing it', function () {
    // Davao Occidental was split from Davao del Sur in 2013; GADM predates that.
    expect(PhilippineGeo::provinceLabelForGid('PHL.28_1'))
        ->toBe('Davao del sur / Davao occidental')
        ->and(PhilippineGeo::provinceLabelForGid('PHL.1_1'))->toBe('Abra');
});
