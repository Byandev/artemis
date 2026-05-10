<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_ads', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('meta_ads_account_id');
            $table->unsignedBigInteger('meta_ads_campaign_id');
            $table->unsignedBigInteger('meta_ads_set_id');
            $table->unsignedBigInteger('meta_ads_creative_id')->nullable();
            $table->string('name');
            $table->string('status', 32)->nullable();
            $table->string('effective_status', 32)->nullable();
            $table->unsignedBigInteger('created_by_meta_user_id')->nullable();
            $table->timestamp('created_time')->nullable();
            $table->timestamp('updated_time')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('meta_ads_account_id');
            $table->index('meta_ads_campaign_id');
            $table->index('meta_ads_set_id');
            $table->index('meta_ads_creative_id');
            $table->index('created_by_meta_user_id');
            $table->index('status');
            $table->index('effective_status');
            $table->index(['meta_ads_account_id', 'effective_status']);
            $table->index(['meta_ads_set_id', 'effective_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_ads');
    }
};
