<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records where a person's access was read from.
     *
     *  portfolio      — Business Manager's assigned_users list. Complete.
     *  connected_user — reverse lookup over the Facebook users who connected
     *                   Artemis. Only available fallback for ad accounts in no
     *                   business portfolio, and necessarily partial: it can't
     *                   see anyone who never connected.
     *
     * `source_business_id` is added here too, defensively. It was originally
     * appended to the create-table migration after that migration had already
     * run on some environments, so those tables never got the column and never
     * will — the create migration won't run twice. Adding it here, guarded,
     * heals them without disturbing environments that already have it.
     *
     * No ->after() on either column: it would reference a column that may not
     * exist yet, and physical column order buys us nothing.
     */
    public function up(): void
    {
        Schema::table('meta_ads_account_people', function (Blueprint $table) {
            if (! Schema::hasColumn('meta_ads_account_people', 'source_business_id')) {
                $table->string('source_business_id')->nullable();
            }

            if (! Schema::hasColumn('meta_ads_account_people', 'source')) {
                $table->string('source', 32)->default('portfolio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_account_people', function (Blueprint $table) {
            if (Schema::hasColumn('meta_ads_account_people', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
