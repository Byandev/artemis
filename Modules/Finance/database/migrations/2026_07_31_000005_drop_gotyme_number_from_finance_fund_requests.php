<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The GoTyme number is no longer captured on a fund request. Guarded so it is
 * safe against a database where the column has already been dropped by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('finance_fund_requests', 'gotyme_number')) {
            Schema::table('finance_fund_requests', function (Blueprint $table) {
                $table->dropColumn('gotyme_number');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('finance_fund_requests', 'gotyme_number')) {
            Schema::table('finance_fund_requests', function (Blueprint $table) {
                $table->string('gotyme_number')->nullable();
            });
        }
    }
};
