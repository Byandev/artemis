<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            if (! Schema::hasColumn('pancake_order_for_delivery', 'assignee_user_id')) {
                $table->unsignedBigInteger('assignee_user_id')->nullable()->after('assignee_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            if (Schema::hasColumn('pancake_order_for_delivery', 'assignee_user_id')) {
                $table->dropColumn('assignee_user_id');
            }
        });
    }
};
