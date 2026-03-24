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
        try {
            Schema::table('pancake_order_phone_number_reports', function (Blueprint $table) {
                $table->dropUnique(['order_id', 'phone_number']);
            });
        } catch (\Throwable $th) {

        }

        try {
            Schema::table('pancake_order_phone_number_reports', function (Blueprint $table) {
                $table->unique(['order_id', 'phone_number', 'type']);
            });
        } catch (\Throwable $th) {

        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pancake_order_phone_number_reports', function (Blueprint $table) {

        });
    }
};
