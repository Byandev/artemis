<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('meta_account_id')->unique();
            $table->string('name');
            $table->string('currency', 8)->nullable();
            $table->string('timezone_name')->nullable();
            $table->string('country_code', 8)->nullable();
            $table->integer('account_status')->nullable();
            $table->string('business_id')->nullable();
            $table->string('business_name')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_accounts');
    }
};
