<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A deficit typed in by hand rather than read off last month's statement.
     *
     * The months before a workspace started closing statements here were worked
     * out somewhere else — a spreadsheet, usually — so there is no previous row
     * to carry from, and the figure has to be given. It is kept apart from the
     * derived `loss_brought_forward_*` columns rather than overwriting them, so
     * a regenerate can tell "someone said 12,345" from "last month came to
     * -12,345" and doesn't quietly replace the first with the second.
     *
     * Null means nothing was given and the previous month is used, which is not
     * the same as a given zero — that is someone stating the month before broke
     * even, and it holds even once a losing statement exists behind it.
     *
     * One figure, applied to both cost-of-goods bases: it comes off a sheet that
     * knew only one bottom line, and inventing a second would be pretending to
     * a precision the source never had.
     */
    public function up(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->decimal('manual_loss_brought_forward', 14, 2)->nullable()
                ->after('cumulative_profit_bought_cogs');
        });
    }

    public function down(): void
    {
        Schema::table('finance_income_statements', function (Blueprint $table) {
            $table->dropColumn('manual_loss_brought_forward');
        });
    }
};
