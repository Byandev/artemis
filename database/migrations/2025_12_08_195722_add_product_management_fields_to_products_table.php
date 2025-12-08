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
        Schema::table('products', function (Blueprint $table) {
            $table->string('name')->after('owner_id'); // Product Name (Product 1, Product 2, etc.)
            $table->string('code')->unique()->after('name'); // Product Code (ABC, ABD, ABE)
            $table->string('category')->after('code'); // Category (Health And Wellness, etc.)
            $table->enum('status', ['Scaling', 'Testing', 'Failed', 'Inactive'])->default('Testing')->after('category'); // Status
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['name', 'code', 'category', 'status']);
        });
    }
};
