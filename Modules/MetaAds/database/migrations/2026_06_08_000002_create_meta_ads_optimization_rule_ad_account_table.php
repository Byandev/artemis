<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_optimization_rule_ad_account', function (Blueprint $table) {
            $table->unsignedBigInteger('meta_ads_optimization_rule_id');
            $table->unsignedBigInteger('meta_ads_account_id');

            // Composite primary key doubles as the rule↔account uniqueness
            // constraint and satisfies MySQL's sql_require_primary_key (enforced
            // on managed MySQL such as DigitalOcean).
            $table->primary(['meta_ads_optimization_rule_id', 'meta_ads_account_id']);

            // Explicit short names — auto-generated identifiers exceed MySQL's
            // 64-character limit.
            $table->foreign('meta_ads_optimization_rule_id', 'maoraa_rule_id_foreign')
                ->references('id')->on('meta_ads_optimization_rules')->onDelete('cascade');
            $table->foreign('meta_ads_account_id', 'maoraa_account_id_foreign')
                ->references('id')->on('meta_ads_accounts')->onDelete('cascade');
        });

        // Carry any existing single-account assignments into the pivot.
        if (Schema::hasColumn('meta_ads_optimization_rules', 'meta_ads_account_id')) {
            foreach (
                DB::table('meta_ads_optimization_rules')
                    ->whereNotNull('meta_ads_account_id')
                    ->get(['id', 'meta_ads_account_id']) as $rule
            ) {
                DB::table('meta_ads_optimization_rule_ad_account')->insertOrIgnore([
                    'meta_ads_optimization_rule_id' => $rule->id,
                    'meta_ads_account_id' => $rule->meta_ads_account_id,
                ]);
            }

            Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
                $table->dropForeign('maor_ad_account_id_foreign');
                $table->dropIndex('maor_account_active_idx');
                $table->dropColumn('meta_ads_account_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('meta_ads_account_id')->nullable()->after('workspace_id');
        });

        Schema::dropIfExists('meta_ads_optimization_rule_ad_account');
    }
};
