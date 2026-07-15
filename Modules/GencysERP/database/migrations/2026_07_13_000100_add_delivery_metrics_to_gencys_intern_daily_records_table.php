<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_intern_daily_records', function (Blueprint $table) {
            // Daily delivered/returned breakdown, reported alongside the existing
            // rts_rate / rts_amount. Nullable — the ERP omits them on some payloads.
            $table->unsignedInteger('delivered')->nullable()->after('rts_amount');
            $table->decimal('delivered_amount', 15, 2)->nullable()->after('delivered');
            $table->unsignedInteger('returned')->nullable()->after('delivered_amount');
            $table->decimal('returned_amount', 15, 2)->nullable()->after('returned');
        });
    }

    public function down(): void
    {
        Schema::table('gencys_intern_daily_records', function (Blueprint $table) {
            $table->dropColumn([
                'delivered',
                'delivered_amount',
                'returned',
                'returned_amount',
            ]);
        });
    }
};
