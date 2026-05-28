<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creatives_ads_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('creative_id');
            $table->enum('ads_status', ['pending', 'running', 'kill', 'skill'])->default('pending');
            $table->timestamps();

            $table->foreign('creative_id')->references('id')->on('creatives')->cascadeOnDelete();

            $table->index('creative_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creatives_ads_campaigns');
    }
};
