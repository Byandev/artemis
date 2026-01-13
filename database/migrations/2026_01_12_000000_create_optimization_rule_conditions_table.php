<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('optimization_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('optimization_rule_id')->constrained()->onDelete('cascade');
            $table->string('metric'); // e.g., 'spend', 'impressions', 'clicks', 'sales', 'roas'
            $table->enum('operator', ['greater_than', 'less_than', 'equal', 'greater_than_or_equal', 'less_than_or_equal']); // Comparison operator
            $table->decimal('value', 15, 2); // The value to compare against
            $table->timestamps();

            // Indexes for performance
            $table->index('optimization_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('optimization_rule_conditions');
    }
};
