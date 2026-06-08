<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('meta_ads_account_id')->after('workspace_id');

            // Explicit short names — the auto-generated identifiers exceed
            // MySQL's 64-character limit.
            $table->foreign('meta_ads_account_id', 'maor_ad_account_id_foreign')
                ->references('id')
                ->on('meta_ads_accounts')
                ->onDelete('cascade');

            $table->index(['meta_ads_account_id', 'is_active'], 'maor_account_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            $table->dropForeign('maor_ad_account_id_foreign');
            $table->dropIndex('maor_account_active_idx');
            $table->dropColumn('meta_ads_account_id');
        });
    }
};
