<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Which workspace SIM a page sends parcel-journey SMS from when its
            // sms_provider is "sim_gateway" (the in-house Artemis SIM Gateway).
            $table->foreignId('sim_gateway_sim_id')
                ->nullable()
                ->after('sendgate_sim_id')
                ->constrained('sim_gateway_sims')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sim_gateway_sim_id');
        });
    }
};
