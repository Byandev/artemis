<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Nullable: the form is how the catalog is categorized, and every
            // product that predates the feature has none. Deleting a form
            // releases its products rather than taking them with it.
            $table->foreignId('product_form_id')
                ->nullable()
                ->after('category')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_form_id');
        });
    }
};
