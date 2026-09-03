<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_tracker_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // The band a deliverable is grouped under on the page ("Ad spend",
            // "Trackers", …). Free text rather than an enum so a workspace can
            // name its own bands without a migration.
            $table->string('category', 60);
            $table->string('label', 500);
            $table->string('cadence', 16)->default('daily');
            // Short pills rendered on the right of the row ("ERP", "GOTYME").
            $table->json('tags')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'active', 'position'], 'daily_tracker_items_workspace_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_tracker_items');
    }
};
