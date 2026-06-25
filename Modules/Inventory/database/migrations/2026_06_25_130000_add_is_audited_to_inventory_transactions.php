<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            // Marks a row whose remaining_qty was set by a physical audit. That row
            // becomes the anchor the actual-stock offset rides forward from.
            $table->boolean('is_audited')->default(false)->after('remaining_qty');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn('is_audited');
        });
    }
};
