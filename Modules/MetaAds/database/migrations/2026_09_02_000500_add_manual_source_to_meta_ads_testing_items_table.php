<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a campaign or ad set be tracked by hand, for anything Meta's sync
     * does not carry.
     *
     * A synced item is a pointer: item_id names a real campaign / ad set, and
     * the name, start date, account and page are all read back through it. A
     * manual one has nothing to point at, so it has to carry those itself —
     * hence the plain text columns beside the ids.
     *
     * `source` is what tells the two apart, and everything that reaches into
     * Meta's tables (the insights rollup, the product resolver) keys off it.
     */
    public function up(): void
    {
        Schema::table('meta_ads_testing_items', function (Blueprint $table) {
            $table->enum('source', ['meta', 'manual'])
                ->default('meta')
                ->after('workspace_id');

            // No Meta id to point at when the row is entered by hand. The
            // unique on (workspace, type, item_id) still holds: MySQL lets a
            // nullable unique index take any number of NULLs, so manual rows
            // never collide with each other.
            $table->unsignedBigInteger('item_id')->nullable()->change();

            // What a synced row reads off the campaign / ad set, a manual row
            // stores directly.
            $table->string('name')->nullable()->after('item_id');
            $table->string('account_name')->nullable()->after('meta_ads_account_id');
            $table->string('page_name')->nullable()->after('account_name');
            $table->date('start_date')->nullable()->after('page_name');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_testing_items', function (Blueprint $table) {
            $table->dropColumn(['source', 'name', 'account_name', 'page_name', 'start_date']);
            $table->unsignedBigInteger('item_id')->nullable(false)->change();
        });
    }
};
