<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_unit_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('unit_code');
            $table->string('sku')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->timestamps();

            // A unit code is unique per workspace — the upsert key on sync.
            $table->unique(['workspace_id', 'unit_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_unit_codes');
    }
};
