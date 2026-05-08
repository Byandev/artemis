<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_page_daily_metrics', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('page_id');
            $table->date('date');

            $table->unsignedInteger('confirmed_count')->default(0);
            $table->unsignedInteger('shipped_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('entered_returning_count')->default(0);
            $table->unsignedInteger('returned_count')->default(0);

            $table->decimal('confirmed_amount', 18, 2)->default(0);
            $table->decimal('shipped_amount', 18, 2)->default(0);
            $table->decimal('delivered_amount', 18, 2)->default(0);
            $table->decimal('entered_returning_amount', 18, 2)->default(0);
            $table->decimal('returned_amount', 18, 2)->default(0);

            $table->timestamps();

            $table->unique(['workspace_id', 'page_id', 'date'], 'wpdm_unique');
            $table->index(['workspace_id', 'date'], 'wpdm_workspace_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_page_daily_metrics');
    }
};
