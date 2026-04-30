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
        });
    }

    public function down(): void
    {
        Schema::table('workspace_page_daily_metrics', function (Blueprint $table) {
            $table->dropColumn('new_customer_count');
        });
    }
};
