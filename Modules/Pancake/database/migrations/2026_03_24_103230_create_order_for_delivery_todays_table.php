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
        Schema::create('pancake_order_for_delivery', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('workspace_id');
            $table->string('status');
            $table->string('rider_name');
            $table->string('rider_phone');
            $table->unsignedBigInteger('caller_id')->nullable();
            $table->uuid('conferrer_id')->nullable();

            $table->foreign('order_id')
                ->references('id')
                ->on('pancake_orders')
                ->cascadeOnDelete();

            $table->foreign('page_id')
                ->references('id')
                ->on('pages')
                ->cascadeOnDelete();

            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->cascadeOnDelete();

            $table->foreign('caller_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->date('delivery_date');

            $table->timestamps();

            $table->index(['workspace_id', 'page_id', 'shop_id'], 'pofc_wid_pid_sid_idx');
            $table->index(['workspace_id', 'page_id', 'shop_id', 'caller_id'], 'pofc_wid_pid_sid_cid_idx');
            $table->index(['workspace_id', 'page_id', 'shop_id', 'caller_id', 'status'], 'pofc_wid_pid_sid_cid_s_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pancake_order_for_delivery');
    }
};
