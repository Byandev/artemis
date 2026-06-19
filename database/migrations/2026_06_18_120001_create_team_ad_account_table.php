<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_ad_account', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('meta_ads_account_id');
            $table->enum('access_level', ['view', 'manage'])->default('view');
            $table->timestamps();

            $table->primary(['team_id', 'meta_ads_account_id']);
            $table->foreign('meta_ads_account_id')
                ->references('id')
                ->on('meta_ads_accounts')
                ->cascadeOnDelete();
            $table->index('meta_ads_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_ad_account');
    }
};
