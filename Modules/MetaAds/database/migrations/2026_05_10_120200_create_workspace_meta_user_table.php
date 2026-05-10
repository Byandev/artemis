<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_workspace_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('meta_ads_user_id');
            $table->unsignedBigInteger('connected_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'meta_ads_user_id']);
            $table->index('meta_ads_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_workspace_user');
    }
};
