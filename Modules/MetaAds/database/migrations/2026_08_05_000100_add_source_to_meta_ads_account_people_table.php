<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a person's access was read from.
     *
     *  portfolio      — Business Manager's assigned_users list. Complete.
     *  connected_user — reverse lookup over the Facebook users who connected
     *                   Artemis. Only available fallback for ad accounts in no
     *                   business portfolio, and necessarily partial: it can't
     *                   see anyone who never connected.
     */
    public function up(): void
    {
        Schema::table('meta_ads_account_people', function (Blueprint $table) {
            $table->string('source', 32)->default('portfolio')->after('source_business_id');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_account_people', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
