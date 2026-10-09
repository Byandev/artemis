<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per order the auto-fill webhook picked up: what it read, what it
     * matched, and what it did about it (or why it didn't). Keyed on Pancake's
     * own order id, since a brand-new order is usually not synced yet.
     */
    public function up(): void
    {
        Schema::create('pancake_address_autofills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->index();
            $table->string('pancake_order_id');
            $table->string('page_id')->nullable();
            $table->string('conversation_id')->nullable();
            // queued, updated, dry_run, needs_review, no_address, skipped, failed
            $table->string('status', 20)->index();
            $table->string('reason')->nullable();
            $table->json('result')->nullable();
            // What Pancake sent, kept so the webhook's real shape can be checked.
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'pancake_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pancake_address_autofills');
    }
};
