<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('price_php', 10, 2)->default(0);
            $table->unsignedInteger('order_limit')->nullable();
            $table->unsignedInteger('page_limit')->nullable();
            $table->unsignedInteger('data_retention_months');
            $table->string('analytics_tier');
            $table->decimal('parcel_journey_rate_php', 10, 2)->nullable();
            $table->boolean('parcel_journey_sms_enabled')->default(false);
            $table->string('support_tier');
            $table->unsignedInteger('trial_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
