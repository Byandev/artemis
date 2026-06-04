<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('parcel_journey_notifications'))
            ->pluck('name');

        if (! $indexes->contains('uniq_pjn_parcel_journey_id')) {
            return;
        }

        Schema::table('parcel_journey_notifications', function (Blueprint $table) {
            // Drop the FK so MySQL releases its hold on the unique index.
            $table->dropForeign('parcel_journey_notifications_parcel_journey_id_foreign');
            $table->dropUnique('uniq_pjn_parcel_journey_id');

            // Re-add the FK — MySQL will auto-create a plain index to back it.
            $table->foreign('parcel_journey_id')
                ->references('id')
                ->on('parcel_journeys')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        //
    }
};
