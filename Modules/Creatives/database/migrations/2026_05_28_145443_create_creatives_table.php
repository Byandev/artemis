<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creatives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->date('creative_date');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('format', ['video', 'image']);
            $table->text('script')->nullable();
            $table->string('picture_url')->nullable();
            $table->string('reference_link')->nullable();
            $table->text('caption')->nullable();
            $table->string('headline')->nullable();
            $table->unsignedBigInteger('creator_id');
            $table->text('notes')->nullable();
            $table->enum('ads_status', ['pending', 'running', 'kill', 'scale'])->default('pending');
            $table->string('ads_manager_link')->nullable();
            $table->text('ads_remarks')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('creator_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['workspace_id', 'creator_id']);
            $table->index(['workspace_id', 'creative_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creatives');
    }
};
