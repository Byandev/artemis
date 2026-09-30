<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a fund request can be raised against the type. New types opt in;
     * the ones already there stay requestable, so the fund request form keeps
     * offering what it offered before.
     */
    public function up(): void
    {
        Schema::table('finance_transaction_types', function (Blueprint $table) {
            $table->boolean('fund_requestable')->default(false)->after('opex_allocation_basis');
        });

    }

    public function down(): void
    {
        Schema::table('finance_transaction_types', function (Blueprint $table) {
            $table->dropColumn('fund_requestable');
        });
    }
};
