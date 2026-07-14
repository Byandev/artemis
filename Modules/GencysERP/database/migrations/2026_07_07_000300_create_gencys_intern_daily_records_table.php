<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gencys_intern_daily_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gencys_intern_id')->constrained('gencys_interns')->cascadeOnDelete();

            $table->date('record_date')->nullable();

            // Daily metrics as the ERP reports them.
            $table->decimal('sales', 15, 2)->nullable();
            $table->decimal('roas', 10, 2)->nullable();
            $table->decimal('ad_spent', 15, 2)->nullable();
            $table->decimal('rts_rate', 8, 2)->nullable();
            $table->decimal('rts_amount', 15, 2)->nullable();

            $table->timestamps();

            // Upsert key: one record per intern per day. Names are given
            // explicitly to stay under MySQL's 64-char identifier limit.
            $table->unique(['workspace_id', 'gencys_intern_id', 'record_date'], 'gidr_ws_intern_date_unique');
            $table->index(['workspace_id', 'record_date'], 'gidr_ws_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gencys_intern_daily_records');
    }
};
