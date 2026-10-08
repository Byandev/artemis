<?php

use Illuminate\Support\Facades\Http;
use Modules\Pancake\Models\Commune;
use Modules\Pancake\Models\District;
use Modules\Pancake\Models\Province;

function fakePancakeGeo(string $provinceName = 'Abra'): void
{
    Http::fake([
        'pos.pages.fm/api/v1/geo/provinces*' => Http::response(['success' => true, 'data' => [
            ['id' => '63_598', 'name' => $provinceName, 'country_code' => 63, 'new_id' => null, 'name_en' => $provinceName, 'region_type' => 'North Luzon'],
        ]]),
        'pos.pages.fm/api/v1/geo/districts*' => Http::response(['success' => true, 'data' => [
            ['id' => '63_598685', 'name' => 'Bangued', 'postcode' => [2800], 'province_id' => '63_598', 'name_en' => 'Bangued'],
        ]]),
        'pos.pages.fm/api/v1/geo/communes*' => Http::response(['success' => true, 'data' => [
            ['id' => '63_59868519217', 'name' => 'Agtangao', 'district_id' => '63_598685', 'postcode' => null, 'new_id' => null, 'province_id' => '63_598', 'name_en' => 'Agtangao'],
            ['id' => '63_59868518015', 'name' => 'Angad', 'district_id' => '63_598685', 'postcode' => null, 'new_id' => null, 'province_id' => '63_598', 'name_en' => 'Angad'],
        ]]),
    ]);
}

it('saves provinces, districts and communes from Pancake', function () {
    fakePancakeGeo();

    $this->artisan('pancake:sync-geo')->assertSuccessful();

    expect(Province::find('63_598'))
        ->name->toBe('Abra')
        ->region_type->toBe('North Luzon');

    $district = District::find('63_598685');
    expect($district->province_id)->toBe('63_598')
        ->and($district->postcode)->toBe([2800]);

    expect(Commune::where('district_id', '63_598685')->count())->toBe(2)
        ->and(Commune::find('63_59868519217')->district->name)->toBe('Bangued');
});

it('updates rows in place on re-run instead of duplicating', function () {
    Province::create(['id' => '63_598', 'country_code' => 63, 'name' => 'Abra']);
    Commune::create(['id' => '63_59868519217', 'province_id' => '63_598', 'district_id' => '63_598685', 'name' => 'Agtangao']);

    fakePancakeGeo('Abra Renamed');
    $this->artisan('pancake:sync-geo')->assertSuccessful();

    expect(Province::count())->toBe(1)
        ->and(Province::find('63_598')->name)->toBe('Abra Renamed')
        ->and(Commune::count())->toBe(2);
});
