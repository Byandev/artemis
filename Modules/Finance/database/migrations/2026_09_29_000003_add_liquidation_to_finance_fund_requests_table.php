<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the requester has to liquidate the funds released — account for
     * how they were spent — and by when. The deadline is only set when
     * liquidation is required.
     */
    public function up(): void
    {
        Schema::table('finance_fund_requests', function (Blueprint $table) {
            $table->boolean('liquidation_required')->default(false)->after('amount_requested');
            $table->date('liquidation_deadline')->nullable()->after('liquidation_required');
        });
    }

    public function down(): void
    {
        Schema::table('finance_fund_requests', function (Blueprint $table) {
            $table->dropColumn(['liquidation_required', 'liquidation_deadline']);
        });
    }
};
