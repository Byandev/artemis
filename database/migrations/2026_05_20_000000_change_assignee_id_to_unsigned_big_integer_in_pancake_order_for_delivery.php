<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Map Pancake user UUID → system user ID via pancake_users.user_id
        DB::statement('
            UPDATE pancake_order_for_delivery pofd
            INNER JOIN pancake_users pu ON pu.id = pofd.assignee_id
            SET pofd.assignee_id = pu.user_id
            WHERE pofd.assignee_id IS NOT NULL AND pu.user_id IS NOT NULL
        ');

        DB::statement("
            UPDATE pancake_order_for_delivery
            SET assignee_id = NULL
            WHERE assignee_id IS NOT NULL AND assignee_id NOT REGEXP '^[0-9]+$'
        ");

        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->unsignedBigInteger('assignee_id')->nullable()->change();
        });

        // Map call_logs user_id the same way
        DB::statement('
            UPDATE call_logs cl
            INNER JOIN pancake_users pu ON pu.id = cl.user_id
            SET cl.user_id = pu.user_id
            WHERE pu.user_id IS NOT NULL
        ');

        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropUnique('call_logs_unique_call');
            $table->dropIndex('idx_call_logs_workspace_user_date_phone');
        });

        DB::statement("DELETE FROM call_logs WHERE user_id NOT REGEXP '^[0-9]+$'");

        Schema::table('call_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->change();

            $table->unique(
                ['workspace_id', 'user_id', 'phone_number', 'call_date', 'call_time'],
                'call_logs_unique_call'
            );
            $table->index(
                ['workspace_id', 'user_id', 'call_date', 'phone_number'],
                'idx_call_logs_workspace_user_date_phone'
            );
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropUnique('call_logs_unique_call');
            $table->dropIndex('idx_call_logs_workspace_user_date_phone');
        });

        Schema::table('call_logs', function (Blueprint $table) {
            $table->uuid('user_id')->change();

            $table->unique(
                ['workspace_id', 'user_id', 'phone_number', 'call_date', 'call_time'],
                'call_logs_unique_call'
            );
            $table->index(
                ['workspace_id', 'user_id', 'call_date', 'phone_number'],
                'idx_call_logs_workspace_user_date_phone'
            );
        });

        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->uuid('assignee_id')->nullable()->change();
        });
    }
};
