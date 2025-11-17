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
        Schema::create('ad_records', function (Blueprint $table) {
            $table->unsignedBigInteger('ad_id');
            $table->date('date');
            $table->unsignedBigInteger('ad_account_id');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('ad_set_id');
            $table->unsignedBigInteger('reach');
            $table->unsignedBigInteger('clicks');
            $table->unsignedBigInteger('impressions');
            $table->float('spend');
            $table->float('sales');
            $table->unique(['ad_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_records');
    }
};
