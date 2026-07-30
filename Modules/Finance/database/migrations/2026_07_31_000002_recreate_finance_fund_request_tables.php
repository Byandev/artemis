<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "request fund" tables were created under a backwards name; the models and
 * every table created after this one use "fund request". Rather than rename in
 * place, drop the old-name tables and recreate them under the final names,
 * exactly as the schema has accumulated by this point: `charge_to` has already
 * been moved to its pivot (dropped from the parent), while `template`,
 * `gotyme_number` and `date_needed` are still present (a later migration drops
 * them).
 *
 * Two shape changes ride along: the line-items table is not recreated (it has
 * been dropped in favour of the product-share table), and the charge-to pivot
 * comes back as `finance_fund_request_user_shares` (the users a request's amount
 * is shared among, mirroring the product shares). Guarded so it is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropAll(['finance_request_fund_products', 'finance_request_fund_charge_to', 'finance_request_fund_items', 'finance_request_funds']);

        $this->createFunds('finance_fund_requests');
        $this->createUserShares('finance_fund_request_user_shares', 'finance_fund_requests');
        // Long table name — the generated index names would exceed MySQL's
        // 64-character identifier limit, so they are named explicitly.
        $this->createProducts('finance_fund_request_product_shares', 'finance_fund_requests', 'fund_request_product_unique', 'fund_request_product_sort_idx');
    }

    public function down(): void
    {
        $this->dropAll(['finance_fund_request_product_shares', 'finance_fund_request_user_shares', 'finance_fund_requests']);

        $this->createFunds('finance_request_funds');
        $this->createItems('finance_request_fund_items', 'finance_request_funds');
        $this->createUserShares('finance_request_fund_charge_to', 'finance_request_funds');
        $this->createProducts('finance_request_fund_products', 'finance_request_funds', null, null);
    }

    /** Drop the given tables in order (callers pass children before parents). */
    private function dropAll(array $tables): void
    {
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * The fund request parent, as it stands here: `charge_to` already moved to
     * its pivot, `template` / `gotyme_number` / `date_needed` not yet dropped.
     */
    private function createFunds(string $table): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('template')->default('blank');
            $table->date('request_date');
            $table->string('reference_no');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('gotyme_number')->nullable();
            $table->text('purpose');
            $table->decimal('amount_requested', 15, 2);
            $table->date('date_needed')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'reference_no']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'template']);
        });
    }

    /** The ad-budget line items (recreated only when rolling back). */
    private function createItems(string $table, string $parent): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) use ($parent) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained($parent)->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('item_label');
            $table->unsignedInteger('creatives_running')->default(0);
            $table->decimal('budget_per_day', 15, 2)->default(0);
            $table->decimal('days', 8, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['fund_request_id', 'sort_order']);
        });
    }

    /** A user's share of the amount requested (formerly the charge-to pivot). */
    private function createUserShares(string $table, string $parent): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) use ($parent) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained($parent)->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['fund_request_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * A product's share of the amount requested. Index names are passed in so the
     * long-named table can stay under MySQL's 64-character identifier limit.
     */
    private function createProducts(string $table, string $parent, ?string $uniqueName, ?string $sortIndexName): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) use ($parent, $uniqueName, $sortIndexName) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained($parent)->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_label', 191);
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['fund_request_id', 'product_id'], $uniqueName);
            $table->index(['fund_request_id', 'sort_order'], $sortIndexName);
        });
    }
};
