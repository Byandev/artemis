<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gencys_daily_sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->string('order_no')->nullable();
            $table->dateTime('order_date')->nullable();
            $table->string('csr')->nullable();
            $table->string('verifier_name')->nullable();
            $table->string('upsell_by')->nullable();
            $table->string('contact')->nullable();
            $table->text('order_details')->nullable();
            $table->integer('total_qty')->nullable();
            $table->string('page')->nullable();
            $table->string('platform')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('parcel_status')->nullable();
            $table->string('order_status')->nullable();
            $table->dateTime('encoded_date')->nullable();
            $table->dateTime('parcel_updated_date')->nullable();
            $table->date('shipped_out_date')->nullable();
            $table->dateTime('date_added')->nullable();
            $table->decimal('price_upsell', 12, 2)->nullable();
            $table->string('intern_brands_name')->nullable();
            $table->decimal('total_cog', 12, 2)->nullable();

            $table->timestamps();

            // Idempotent upserts per workspace + tracking number (nulls don't
            // collide in MySQL, so untracked rows are simply inserted).
            $table->unique(['workspace_id', 'tracking_number']);
            $table->index(['workspace_id', 'order_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gencys_daily_sales_orders');
    }
};
