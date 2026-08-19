<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the four-hourly Meta budget snapshot may overwrite this page's daily
 * ad budget.
 *
 * Opt-in: defaults to false, so no page has its budget replaced until someone
 * turns it on for that page. A budget typed on the Pages screen stays put by
 * default, and switching a page to Auto is a deliberate choice to let Meta's
 * figure win from then on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('auto_update_ad_budget')->default(false)->after('pancake_token');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('auto_update_ad_budget');
        });
    }
};
