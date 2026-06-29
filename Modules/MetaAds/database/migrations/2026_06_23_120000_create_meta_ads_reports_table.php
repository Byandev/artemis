<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            // What the report was created for. Mirrors the SuperAds "Create report"
            // modal options; drives the builder's default config.
            $table->enum('kind', ['top_performers', 'custom_groups', 'categorization'])
                ->default('top_performers');
            // Saved builder configuration: accounts, date range, group_by, metrics,
            // sort, and filters. Read back to restore the report on open.
            $table->json('config');
            $table->timestamps();

            $table->index(['workspace_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_reports');
    }
};
