<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A carried deficit belongs to a person, not to one of their products.
     *
     * An advertiser's products are netted against each other within the month —
     * a losing one has already eaten into what the winners earned them — so
     * carrying that same product's loss into the next month charges it twice:
     * once by shrinking this month's commission base, again by shrinking next
     * month's. Only the person's own position at the end of the month can be
     * carried, and only when it is negative.
     *
     * Dropping the column rather than agreeing not to use it is the point: a
     * rule that lives in someone's head gets broken on a busy day, and this one
     * is invisible when it is — the figures still add up, they are just wrong.
     * With no product to tag, the mistake cannot be made.
     *
     * Entries already made per product are folded into one per person, which is
     * the figure that would have been meant.
     */
    public function up(): void
    {
        $folded = DB::table('finance_income_loss_carryovers')
            ->selectRaw('workspace_id, period_month, user_id, SUM(amount) as amount')
            ->groupBy('workspace_id', 'period_month', 'user_id')
            ->get();

        DB::table('finance_income_loss_carryovers')->delete();

        Schema::table('finance_income_loss_carryovers', function (Blueprint $table) {
            $table->dropUnique('filc_workspace_month_user_product');
            $table->dropForeign('filc_product');
            $table->dropColumn('product_id');
            $table->unique(['workspace_id', 'period_month', 'user_id'], 'filc_workspace_month_user');
        });

        $now = now();

        foreach ($folded as $row) {
            DB::table('finance_income_loss_carryovers')->insert([
                'workspace_id' => $row->workspace_id,
                'period_month' => $row->period_month,
                'user_id' => $row->user_id,
                'amount' => $row->amount,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('finance_income_loss_carryovers', function (Blueprint $table) {
            $table->dropUnique('filc_workspace_month_user');
            $table->foreignId('product_id')->nullable()->after('user_id')
                ->constrained('products', indexName: 'filc_product')
                ->nullOnDelete();
            $table->unique(
                ['workspace_id', 'period_month', 'user_id', 'product_id'],
                'filc_workspace_month_user_product',
            );
        });
    }
};
