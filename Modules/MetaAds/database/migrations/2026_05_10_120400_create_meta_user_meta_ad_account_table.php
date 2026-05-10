<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_user_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_ads_user_id')->constrained('meta_ads_users')->cascadeOnDelete();
            $table->foreignId('meta_ads_account_id')->constrained('meta_ads_accounts')->cascadeOnDelete();
            $table->json('permitted_tasks')->nullable();
            $table->timestamps();

            $table->unique(['meta_ads_user_id', 'meta_ads_account_id'], 'meta_ads_user_account_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_user_account');
    }
};
