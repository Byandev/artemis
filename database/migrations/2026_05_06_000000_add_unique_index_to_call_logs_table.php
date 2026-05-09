<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove existing duplicates — keep the row with the lowest id
        DB::statement('
            DELETE c1 FROM call_logs c1
            INNER JOIN call_logs c2
            WHERE c1.id > c2.id
              AND c1.workspace_id = c2.workspace_id
              AND c1.user_id = c2.user_id
              AND c1.phone_number = c2.phone_number
              AND c1.call_date = c2.call_date
              AND c1.call_time = c2.call_time
        ');

        Schema::table('call_logs', function (Blueprint $table) {
            $table->unique(
                ['workspace_id', 'user_id', 'phone_number', 'call_date', 'call_time'],
                'call_logs_unique_call'
            );
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropUnique('call_logs_unique_call');
        });
    }
};
