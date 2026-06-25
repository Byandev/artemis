<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_custom_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name');
            // Named, rule-defined buckets. Each group:
            // { name, match: 'all'|'any', rules: [{ field:'name', op, value }] }.
            // An ad is bucketed into the first group whose rules it matches.
            $table->json('groups');
            $table->timestamps();

            $table->index(['workspace_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_custom_breakdowns');
    }
};
