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
        Schema::table('shops', function (Blueprint $table) {
            // Rolling RTS rate for the shop over the previous 14 days, weighted
            // by order final_amount (returned pesos over delivered+returned
            // pesos), stored as a fraction (0–1) to match
            // rider_delivery_summary.rts_rate and the city summaries the RMO
            // table renders alongside it. Nullable — a shop with no
            // delivered/returned value in the window has no rate, which is not
            // the same as a rate of zero.
            $table->decimal('rts_snapshot', 8, 4)->nullable()->after('avatar_url');
            $table->timestamp('rts_snapshot_updated_at')->nullable()->after('rts_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['rts_snapshot', 'rts_snapshot_updated_at']);
        });
    }
};
