<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $exists = collect(Schema::getIndexes('parcel_journey_notifications'))
            ->pluck('name')
            ->contains('uniq_pjn_parcel_journey_id');

        if ($exists) {
            return;
        }

        Schema::table('parcel_journey_notifications', function (Blueprint $table) {
            $table->unique('parcel_journey_id', 'uniq_pjn_parcel_journey_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $exists = collect(Schema::getIndexes('parcel_journey_notifications'))
            ->pluck('name')
            ->contains('uniq_pjn_parcel_journey_id');

        if (! $exists) {
            return;
        }

        Schema::table('parcel_journey_notifications', function (Blueprint $table) {
            $table->dropUnique('uniq_pjn_parcel_journey_id');
        });
    }
};
