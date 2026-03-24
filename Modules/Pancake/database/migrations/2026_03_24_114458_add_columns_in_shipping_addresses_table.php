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
        Schema::table('shipping_addresses', function (Blueprint $table) {
            $table->string('province_id')->nullable();
            $table->string('new_province_id')->nullable();
            $table->string('district_id')->nullable();
            $table->string('commune_id')->nullable();

            $table->index('commune_id');
            $table->index('district_id');
            $table->index('province_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_addresses', function (Blueprint $table) {
            //
        });
    }
};
