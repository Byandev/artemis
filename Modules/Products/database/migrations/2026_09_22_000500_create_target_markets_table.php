<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_markets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // Null for a category; set for a sub category. Deleting a category
            // takes its sub categories with it, which is what the confirm on
            // the page warns about.
            $table->foreignId('parent_id')->nullable()->constrained('target_markets')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            // How the page reads the tree: every category for a workspace,
            // then the children of each.
            $table->index(['workspace_id', 'parent_id']);

            // Name uniqueness among siblings is enforced in the request rules
            // rather than here: MySQL treats NULLs as distinct in a unique
            // index, so this would let two top-level "Cardiovascular" through
            // while blocking two sub categories — the inconsistent half of the
            // rule is worse than none.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_markets');
    }
};
