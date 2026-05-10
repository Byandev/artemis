<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 32);
            $table->string('scope_type', 32)->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('status', 16)->default('running');
            $table->unsignedInteger('records_synced')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'started_at']);
            $table->index(['scope_type', 'scope_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_sync_runs');
    }
};
