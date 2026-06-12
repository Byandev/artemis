<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_creatives', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('meta_ads_account_id');
            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('object_type', 32)->nullable();
            $table->string('call_to_action_type')->nullable();
            $table->text('image_url')->nullable();
            $table->string('image_hash')->nullable();
            $table->string('video_id')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->json('object_story_spec')->nullable();
            $table->string('effective_object_story_id')->nullable();
            $table->text('instagram_permalink_url')->nullable();
            $table->string('status', 32)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('meta_ads_account_id');
            $table->index('object_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_creatives');
    }
};
