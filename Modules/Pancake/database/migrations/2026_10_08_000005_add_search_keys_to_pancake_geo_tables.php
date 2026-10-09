<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Pancake\Support\GeoMatcher;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each name pre-normalised the way GeoMatcher compares them ("Sta. Rosa
     * City" → "santa rosa"), so an exact match is one indexed lookup instead of
     * normalising every candidate in PHP on every check. Filled here and again
     * by every `pancake:sync-geo`.
     */
    public function up(): void
    {
        foreach (['pancake_provinces' => null, 'pancake_districts' => 'province_id', 'pancake_communes' => 'district_id'] as $table => $parent) {
            Schema::table($table, function (Blueprint $t) use ($parent) {
                $t->string('search_key')->nullable();
                $t->string('search_key_en')->nullable();

                $t->index($parent ? [$parent, 'search_key'] : ['search_key']);
                $t->index($parent ? [$parent, 'search_key_en'] : ['search_key_en']);
            });
        }

        app(GeoMatcher::class)->refreshSearchKeys();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['pancake_provinces' => null, 'pancake_districts' => 'province_id', 'pancake_communes' => 'district_id'] as $table => $parent) {
            Schema::table($table, function (Blueprint $t) use ($parent) {
                $t->dropIndex($parent ? [$parent, 'search_key'] : ['search_key']);
                $t->dropIndex($parent ? [$parent, 'search_key_en'] : ['search_key_en']);
                $t->dropColumn(['search_key', 'search_key_en']);
            });
        }
    }
};
