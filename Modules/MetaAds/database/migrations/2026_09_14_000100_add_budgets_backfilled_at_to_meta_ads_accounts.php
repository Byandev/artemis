<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_accounts', function (Blueprint $table) {
            // When this account's past ad-set budgets were last rebuilt from
            // Meta's activity log. A timestamp rather than a flag, so it also
            // answers "how stale is that rebuild" — the reconstruction only
            // covers the window it was run with, and dates before it stay empty.
            $table->timestamp('budgets_backfilled_at')->nullable()->after('active_sync');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_accounts', function (Blueprint $table) {
            $table->dropColumn('budgets_backfilled_at');
        });
    }
};
