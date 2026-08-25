<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every SIM Gateway delivery report looks the notification up by `sms_id`
     * (see SimGateway CallbackController::syncParcelNotification). The column
     * was added without an index, so each callback full-scanned this table —
     * which is one of the largest in the schema and grows with every parcel
     * notification. At DLR volume that is enough to peg the database CPU.
     */
    private const INDEX = 'idx_parcel_journey_notifications_sms_id';

    public function up(): void
    {
        if ($this->hasIndex()) {
            return;
        }

        Schema::table('parcel_journey_notifications', function (Blueprint $table) {
            $table->index('sms_id', self::INDEX);
        });
    }

    public function down(): void
    {
        if (! $this->hasIndex()) {
            return;
        }

        Schema::table('parcel_journey_notifications', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }

    private function hasIndex(): bool
    {
        return collect(Schema::getIndexes('parcel_journey_notifications'))
            ->pluck('name')
            ->contains(self::INDEX);
    }
};
