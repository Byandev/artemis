<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pancake_user_id on these report tables is fed from assignee_id / conferrer_id
     * on pancake_order_for_delivery, which can reference Pancake users that are not
     * (yet) present in the locally-synced pancake_users table. The FK rejected those
     * inserts; we keep the column for joins but drop the hard constraint.
     */
    private array $tables = [
        'pancake_user_pos_daily_reports',
        'pancake_user_erp_daily_reports',
        'pancake_user_rmo_daily_reports',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['pancake_user_id']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreign('pancake_user_id')
                    ->references('id')
                    ->on('pancake_users')
                    ->cascadeOnDelete();
            });
        }
    }
};
