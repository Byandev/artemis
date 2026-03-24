<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Pancake\Models\OrderPhoneNumberReport;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pancake_order_phone_number_reports', function (Blueprint $table) {
            $table->string('type')->default('lates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pancake_order_phone_number_reports', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
