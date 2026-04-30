<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_page_daily_metrics', function (Blueprint $table) {
            $table->unsignedInteger('new_customer_count')->default(0)->after('returned_count');
            $table->unsignedInteger('all_customer_count')->default(0)->after('new_customer_count');
            $table->unsignedInteger('old_customer_count')->default(0)->after('all_customer_count');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_page_daily_metrics', function (Blueprint $table) {
            $table->dropColumn(['new_customer_count', 'all_customer_count', 'old_customer_count']);
        });
    }
};
