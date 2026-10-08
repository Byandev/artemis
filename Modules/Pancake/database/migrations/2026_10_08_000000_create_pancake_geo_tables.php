<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pancake's location list. Ids are Pancake's own strings (e.g. "63_598"),
     * the same ones shipping_addresses stores in province_id / district_id /
     * commune_id.
     */
    public function up(): void
    {
        Schema::create('pancake_provinces', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedInteger('country_code')->index();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('region_type')->nullable();
            $table->string('new_id')->nullable();
            $table->timestamps();
        });

        Schema::create('pancake_districts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('province_id')->index();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->json('postcode')->nullable();
            $table->timestamps();
        });

        Schema::create('pancake_communes', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('province_id')->index();
            $table->string('district_id')->index();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->json('postcode')->nullable();
            $table->string('new_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pancake_communes');
        Schema::dropIfExists('pancake_districts');
        Schema::dropIfExists('pancake_provinces');
    }
};
