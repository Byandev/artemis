<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_sets', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('meta_ads_account_id');
            $table->unsignedBigInteger('meta_ads_campaign_id');
            $table->string('name');
            $table->string('status', 32)->nullable();
            $table->string('effective_status', 32)->nullable();
            $table->decimal('daily_budget', 20, 2)->nullable();
            $table->decimal('lifetime_budget', 20, 2)->nullable();
            $table->string('bid_strategy')->nullable();
            $table->string('optimization_goal')->nullable();
            $table->string('billing_event')->nullable();
            $table->json('targeting')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->timestamp('created_time')->nullable();
            $table->timestamp('updated_time')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('meta_ads_account_id');
            $table->index('meta_ads_campaign_id');
            $table->index('status');
            $table->index('effective_status');
            $table->index(['meta_ads_account_id', 'effective_status']);
            $table->index(['meta_ads_campaign_id', 'effective_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_sets');
    }
};
