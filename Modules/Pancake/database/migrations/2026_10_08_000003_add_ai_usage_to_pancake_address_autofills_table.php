<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * What the AI call behind each auto-fill cost, as OpenRouter reported it.
     * Null when no AI call was made (skipped before reading the chat) or the
     * provider did not report it.
     */
    public function up(): void
    {
        Schema::table('pancake_address_autofills', function (Blueprint $table) {
            $table->decimal('ai_cost_usd', 12, 8)->nullable()->after('result');
            $table->unsignedInteger('input_tokens')->nullable()->after('ai_cost_usd');
            $table->unsignedInteger('output_tokens')->nullable()->after('input_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pancake_address_autofills', function (Blueprint $table) {
            $table->dropColumn(['ai_cost_usd', 'input_tokens', 'output_tokens']);
        });
    }
};
