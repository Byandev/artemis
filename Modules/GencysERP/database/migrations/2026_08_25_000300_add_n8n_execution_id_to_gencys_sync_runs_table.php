<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_sync_runs', function (Blueprint $table) {
            // The n8n execution that handled this run, echoed back on the
            // callback. Purely for tracing: when a run fails or comes back short,
            // this is what you paste into n8n to see what actually happened.
            // Nullable because older runs have none and n8n may not send it.
            $table->string('n8n_execution_id', 64)->nullable()->after('attempt');

            $table->index('n8n_execution_id');
        });
    }

    public function down(): void
    {
        Schema::table('gencys_sync_runs', function (Blueprint $table) {
            $table->dropIndex(['n8n_execution_id']);
            $table->dropColumn('n8n_execution_id');
        });
    }
};
