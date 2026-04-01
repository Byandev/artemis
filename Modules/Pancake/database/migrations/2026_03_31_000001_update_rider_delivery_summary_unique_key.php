<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_delivery_summary', function (Blueprint $table) {
            $table->dropUnique(['rider_name']);
            $table->unique(['rider_name', 'rider_phone']);
        });
    }

    public function down(): void
    {
        Schema::table('rider_delivery_summary', function (Blueprint $table) {
            $table->dropUnique(['rider_name', 'rider_phone']);
            $table->unique(['rider_name']);
        });
    }
};
