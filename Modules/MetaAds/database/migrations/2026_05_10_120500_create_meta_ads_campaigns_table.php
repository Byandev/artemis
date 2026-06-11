<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('meta_ads_account_id');
            $table->string('name');
            $table->string('objective')->nullable();
            $table->string('status', 32)->nullable();
            $table->string('effective_status', 32)->nullable();
            $table->string('buying_type', 32)->nullable();
            $table->string('bid_strategy')->nullable();
            $table->decimal('daily_budget', 20, 2)->nullable();
            $table->decimal('lifetime_budget', 20, 2)->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('stop_time')->nullable();
            $table->timestamp('created_time')->nullable();
            $table->timestamp('updated_time')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('meta_ads_account_id');
            $table->index('status');
            $table->index('effective_status');
            $table->index(['meta_ads_account_id', 'effective_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_campaigns');
    }
};
