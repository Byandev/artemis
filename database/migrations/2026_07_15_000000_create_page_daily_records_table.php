<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_daily_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            // Which pipeline produced the row.
            $table->string('source', 16); // 'gencys' | 'artemis'

            // Polymorphic page: either the Pancake page (App\Models\Page) or the
            // Gencys page (Modules\GencysERP\Models\Page). Creates page_type + page_id.
            $table->morphs('page');

            $table->date('date');

            // Core metrics. `sales` is the purchase value → ROAS = sales / ad_spent.
            $table->unsignedInteger('orders')->nullable();
            $table->decimal('sales', 15, 2)->nullable();
            $table->unsignedInteger('delivered')->nullable();
            $table->decimal('delivered_amount', 15, 2)->nullable();
            $table->unsignedInteger('returned')->nullable();
            $table->decimal('returned_amount', 15, 2)->nullable();
            $table->decimal('ad_spent', 15, 2)->nullable();
            $table->decimal('roas', 10, 2)->nullable();

            $table->timestamps();

            // Upsert key: one record per page per day. Explicit names stay under
            // MySQL's 64-char identifier limit.
            $table->unique(['workspace_id', 'page_type', 'page_id', 'date'], 'pdr_ws_page_date_unique');
            $table->index(['workspace_id', 'source', 'date'], 'pdr_ws_source_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_daily_records');
    }
};
