<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_form_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_form_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // The order the sizes were entered in, so the list reads the same
            // way it was typed rather than by id or alphabetically.
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_form_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_form_variants');
    }
};
