<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pancake_user_daily_engagements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('page_id');
            $table->uuid('pancake_user_id');
            $table->date('date');
            $table->string('name')->nullable();
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('old_order_count')->default(0);
            $table->unsignedInteger('customer_engagement_new_inbox')->default(0);
            $table->unsignedInteger('inbox_count')->default(0);
            $table->unsignedInteger('new_customer_replied_count')->default(0);
            $table->unsignedInteger('total_engagement')->default(0);
            $table->timestamps();

            $table->unique(['page_id', 'pancake_user_id', 'date'], 'pancake_user_daily_engagements_unique');
            $table->index(['workspace_id', 'date']);
            $table->index(['pancake_user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pancake_user_daily_engagements');
    }
};
