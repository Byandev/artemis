<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A parcel journey legitimately produces several notifications — a customer
     * SMS, a customer chat, and (on delivery) a rider SMS — so the old
     * single-column UNIQUE(parcel_journey_id) was too strict and had to be
     * dropped. This re-introduces an atomic guard at the right granularity:
     * one row per (journey, channel, recipient). It backstops the application
     * level `firstOrCreate`, so concurrent / retried syncs can no longer create
     * duplicate notifications (and therefore duplicate sends).
     */
    private const INDEX = 'uniq_pjn_journey_type_receiver';

    public function up(): void
    {
        $exists = collect(Schema::getIndexes('parcel_journey_notifications'))
            ->pluck('name')
            ->contains(self::INDEX);

        if ($exists) {
            return;
        }

        // Collapse any pre-existing duplicates before adding the index, keeping
        // the earliest (lowest id) row of each tuple — otherwise creating the
        // unique index would fail on production data.
        DB::statement(<<<'SQL'
            DELETE n1 FROM parcel_journey_notifications n1
            INNER JOIN parcel_journey_notifications n2
                ON n1.parcel_journey_id = n2.parcel_journey_id
                AND n1.type = n2.type
                AND n1.receiver_identity = n2.receiver_identity
                AND n1.id > n2.id
        SQL);

        Schema::table('parcel_journey_notifications', function (Blueprint $table) {
            $table->unique(['parcel_journey_id', 'type', 'receiver_identity'], self::INDEX);
        });
    }

    public function down(): void
    {
        $exists = collect(Schema::getIndexes('parcel_journey_notifications'))
            ->pluck('name')
            ->contains(self::INDEX);

        if (! $exists) {
            return;
        }

        Schema::table('parcel_journey_notifications', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });
    }
};
