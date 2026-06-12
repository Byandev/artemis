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
            $table->unsignedBigInteger('meta_ads_user_id');
            $table->unsignedBigInteger('meta_ads_account_id');
            $table->json('permitted_tasks')->nullable();
            $table->timestamps();

            $table->unique(['meta_ads_user_id', 'meta_ads_account_id'], 'meta_ads_user_account_unique');
            $table->index('meta_ads_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_user_account');
    }
};
