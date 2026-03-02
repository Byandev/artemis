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
        Schema::create('pancake_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number');
            $table->unsignedInteger('status');
            $table->string('status_name');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('workspace_id');
            $table->float('total_amount')->default(0);
            $table->float('final_amount')->default(0);
            $table->float('discount')->default(0);
            $table->unsignedBigInteger('ad_id')->nullable();
            $table->string('fb_id')->nullable();
            $table->uuid('customer_id');
            $table->unsignedInteger('delivery_attempts')->nullable();
            $table->timestamp('first_delivery_attempt')->nullable();
            $table->timestamp('inserted_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('returning_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('shipped_at')->nullable();

            $table->string('tracking_code')->nullable();
            $table->string('parcel_status')->nullable();
            $table->integer('customer_succeed_order_count')->default(0);
            $table->integer('customer_returned_order_count')->default(0);
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->unsignedBigInteger('conferrer_id')->nullable();
            $table->unsignedBigInteger('last_editor_id')->nullable();

            $table->timestamps();

            $table->unique(['order_number', 'page_id', 'workspace_id']);

            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->onDelete('cascade');

            $table->index(['workspace_id', 'page_id'], 'idx_pancake_orders_workspace_page');
            $table->index(['workspace_id', 'shop_id'], 'idx_pancake_orders_workspace_shop');
            $table->index(['workspace_id', 'status'], 'idx_pancake_orders_workspace_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pancake_orders');
    }
};
