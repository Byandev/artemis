<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Pancake\Models\Commune;
use Modules\Pancake\Models\District;
use Modules\Pancake\Models\Province;
use Modules\Pancake\Services\Pancake;
use Modules\Pancake\Support\GeoMatcher;

class SyncPancakeGeo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pancake:sync-geo {--country=63 : Pancake country code (63 = Philippines)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Save Pancake provinces, districts and communes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $provinces = Pancake::listProvinces((int) $this->option('country'));

        Province::upsert(
            collect($provinces)->map(fn (array $p) => [
                'id' => $p['id'],
                'country_code' => $p['country_code'],
                'name' => $p['name'],
                'name_en' => $p['name_en'] ?? null,
                'region_type' => $p['region_type'] ?? null,
                'new_id' => $p['new_id'] ?? null,
            ])->all(),
            ['id'],
            ['country_code', 'name', 'name_en', 'region_type', 'new_id'],
        );

        $districtCount = 0;
        $communeCount = 0;

        $this->withProgressBar($provinces, function (array $province) use (&$districtCount, &$communeCount) {
            $districts = Pancake::listDistricts($province['id']);

            foreach (array_chunk($districts, 500) as $chunk) {
                District::upsert(
                    array_map(fn (array $d) => [
                        'id' => $d['id'],
                        'province_id' => $d['province_id'] ?? $province['id'],
                        'name' => $d['name'],
                        'name_en' => $d['name_en'] ?? null,
                        'postcode' => isset($d['postcode']) ? json_encode($d['postcode']) : null,
                    ], $chunk),
                    ['id'],
                    ['province_id', 'name', 'name_en', 'postcode'],
                );
            }

            $communes = Pancake::listCommunes($province['id']);

            foreach (array_chunk($communes, 500) as $chunk) {
                Commune::upsert(
                    array_map(fn (array $c) => [
                        'id' => $c['id'],
                        'province_id' => $c['province_id'] ?? $province['id'],
                        'district_id' => $c['district_id'],
                        'name' => $c['name'],
                        'name_en' => $c['name_en'] ?? null,
                        'postcode' => isset($c['postcode']) ? json_encode($c['postcode']) : null,
                        'new_id' => $c['new_id'] ?? null,
                    ], $chunk),
                    ['id'],
                    ['province_id', 'district_id', 'name', 'name_en', 'postcode', 'new_id'],
                );
            }

            $districtCount += count($districts);
            $communeCount += count($communes);
        });

        // The pre-normalised names GeoMatcher looks rows up by.
        app(GeoMatcher::class)->refreshSearchKeys();

        $this->newLine();
        $this->info(sprintf('Saved %d provinces, %d districts, %d communes.', count($provinces), $districtCount, $communeCount));

        return self::SUCCESS;
    }
}
