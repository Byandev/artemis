<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_sync_runs', function (Blueprint $table) {
            // The n8n execution this run's data came back from — n8n's own
            // auto-incrementing execution id. Lets a failed run be traced
            // straight to its execution log.
            $table->unsignedBigInteger('n8n_execution_id')->nullable()->after('message');

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
