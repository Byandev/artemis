<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * How many times the chat has been read for this order. A new order is
     * read a few minutes after it arrives and, when the customer has not sent
     * an address yet, once more later — see AutoFillOrderAddress.
     */
    public function up(): void
    {
        Schema::table('pancake_address_autofills', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pancake_address_autofills', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
};
