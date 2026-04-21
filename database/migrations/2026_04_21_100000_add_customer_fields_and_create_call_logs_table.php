<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('rider_phone');
            $table->string('customer_number')->nullable()->after('customer_name');
        });

        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->uuid('user_id');
            $table->string('phone_number');
            $table->string('type');
            $table->unsignedInteger('duration')->default(0);
            $table->timestamp('called_at');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();

            $table->index(['workspace_id', 'user_id']);
            $table->index('phone_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');

        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'customer_number']);

            $table->unsignedInteger('customer_call_attempts')->default(0);
            $table->unsignedInteger('customer_call_duration')->default(0);
            $table->timestamp('customer_last_call')->nullable();
            $table->unsignedInteger('rider_call_attempts')->default(0);
            $table->unsignedInteger('rider_call_duration')->default(0);
            $table->timestamp('rider_last_call')->nullable();
        });
    }
};