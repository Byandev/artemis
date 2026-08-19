<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // Safety-stock buffer, in days, used for the PO QTY column
            // (days_of_coverage × 3-day average) and folded into PO Needed.
            // Defaults to 10 days.
            $table->unsignedInteger('days_of_coverage')->default(10)->after('lead_time');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('days_of_coverage');
        });
    }
};
