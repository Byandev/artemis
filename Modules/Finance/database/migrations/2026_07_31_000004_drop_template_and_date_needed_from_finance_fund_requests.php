<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A fund request is one flexible shape now rather than two fixed templates:
     * any request may carry ad-spend line items, and the products it covers are
     * recorded separately (finance_request_fund_products). The `template` label
     * no longer decides anything, and `date_needed` went unused.
     *
     * The records themselves are untouched — a request that was an Ad Spent one
     * keeps its line items and its total, it just stops being labelled.
     */
    public function up(): void
    {
        // Looked up rather than guessed: MySQL keeps an index's original name
        // through a RENAME TABLE, so this one is still called after the table's
        // former name (see the rename migration).
        $index = collect(Schema::getIndexes('finance_fund_requests'))
            ->first(fn ($index) => in_array('template', $index['columns'], true));

        Schema::table('finance_fund_requests', function (Blueprint $table) use ($index) {
            if (Schema::hasColumn('finance_fund_requests', 'template')) {
                // The index goes first: MySQL will not drop an indexed column.
                if ($index) {
                    $table->dropIndex($index['name']);
                }

                $table->dropColumn('template');
            }

            if (Schema::hasColumn('finance_fund_requests', 'date_needed')) {
                $table->dropColumn('date_needed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_fund_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_fund_requests', 'template')) {
                $table->string('template')->default('blank')->after('workspace_id');
                $table->index(['workspace_id', 'template']);
            }

            if (! Schema::hasColumn('finance_fund_requests', 'date_needed')) {
                $table->date('date_needed')->nullable()->after('amount_requested');
            }
        });
    }
};
