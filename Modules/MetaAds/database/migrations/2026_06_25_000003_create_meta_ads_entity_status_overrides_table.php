<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_entity_status_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->enum('level', ['campaign', 'ad_set', 'ad']);
            $table->unsignedBigInteger('entity_id');

            // Null = no override; the suggested status stands.
            $table->enum('final_status', ['scaling', 'maintain', 'killed'])->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            // One override per entity per workspace. Short name — MySQL 64-char limit.
            $table->unique(['workspace_id', 'level', 'entity_id'], 'maeso_entity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_entity_status_overrides');
    }
};
