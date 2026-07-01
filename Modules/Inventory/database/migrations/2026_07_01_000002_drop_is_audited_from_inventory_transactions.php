<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // is_audited backed the manual "remaining qty" audit + recalculateActualStock(), both
    // retired now that ERP counts sync in as-is and manual corrections use discrepancies.
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn('is_audited');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->boolean('is_audited')->default(false)->after('remaining_qty');
        });
    }
};
