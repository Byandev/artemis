<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rdps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // Who built the brief — the "Created by" column on the list.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // The brief. Nulled rather than cascaded when the taxonomy behind
            // them is deleted: a brief already sent to the lab still describes
            // what was asked for, and losing the row would lose that.
            $table->foreignId('product_form_id')->nullable()->constrained('product_forms')->nullOnDelete();
            $table->foreignId('target_market_id')->nullable()->constrained('target_markets')->nullOnDelete();
            $table->foreignId('target_market_sub_id')->nullable()->constrained('target_markets')->nullOnDelete();

            $table->string('name');

            // Generated later by the AI step; a column now so the builder has
            // somewhere to put it when that lands.
            $table->text('positioning')->nullable();

            // One item per line, as typed.
            $table->text('claims')->nullable();
            $table->text('active_ingredients')->nullable();
            $table->text('additional_instruction')->nullable();

            $table->timestamps();

            // How the list reads: a workspace's briefs, newest first.
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rdps');
    }
};
