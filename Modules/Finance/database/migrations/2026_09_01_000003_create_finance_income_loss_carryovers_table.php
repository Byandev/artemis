<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A deficit carried into a month, entered against one seller and one
     * product — the finest grain there is, so it adds up the same way every
     * other figure does: across products for a person, across people for a
     * product, and across both for the month.
     *
     * Kept in its own table rather than on the statement slices because those
     * are snapshots: every save and regenerate deletes and rebuilds them, and a
     * figure someone typed must not go with them. Keyed by workspace and month
     * rather than by statement id for the same reason — the figure describes
     * the months before, and outlives any particular closing of this one. It
     * can also be entered before the month has ever been closed.
     *
     * A null user or product means the deficit belongs to that side's
     * unattributed bucket, matching how the slices report a seller nobody is
     * linked to or an item that resolves to no product.
     *
     * `amount` is what is owed, held positive: a carryover is a hole to fill,
     * and a negative one would be a credit, which is not a thing this models.
     */
    public function up(): void
    {
        Schema::create('finance_income_loss_carryovers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workspace_id')
                ->constrained('workspaces', indexName: 'filc_workspace')
                ->cascadeOnDelete();

            // First of the month the deficit is carried INTO.
            $table->date('period_month');

            $table->foreignId('user_id')->nullable()
                ->constrained('users', indexName: 'filc_user')
                ->nullOnDelete();

            $table->foreignId('product_id')->nullable()
                ->constrained('products', indexName: 'filc_product')
                ->nullOnDelete();

            $table->decimal('amount', 14, 2)->default(0);

            $table->timestamps();

            $table->unique(
                ['workspace_id', 'period_month', 'user_id', 'product_id'],
                'filc_workspace_month_user_product',
            );
            $table->index(['workspace_id', 'period_month'], 'filc_workspace_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_income_loss_carryovers');
    }
};
