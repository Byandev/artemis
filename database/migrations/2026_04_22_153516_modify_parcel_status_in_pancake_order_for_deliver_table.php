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
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->string('parcel_status')
                ->nullable()
                ->default(null)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            //
        });
    }
};
