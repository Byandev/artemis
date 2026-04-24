<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_customer_activity_daily', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('workspace_id');
            $table->uuid('customer_id');
            $table->date('date');
            $table->unsignedBigInteger('page_id');

            $table->unsignedInteger('confirmed_count')->default(0);
            $table->decimal('confirmed_spend', 18, 2)->default(0);

            $table->timestamps();

            $table->unique(['workspace_id', 'customer_id', 'date', 'page_id'], 'wcad_workspace_customer_date_page_unique');
            $table->index(['workspace_id', 'date'], 'wcad_workspace_date_idx');
            $table->index(['workspace_id', 'date', 'page_id'], 'wcad_workspace_date_page_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_customer_activity_daily');
    }
};
