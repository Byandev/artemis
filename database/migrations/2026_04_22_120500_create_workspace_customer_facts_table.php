<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_customer_facts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('workspace_id');
            $table->uuid('customer_id');

            $table->dateTime('first_confirmed_at')->nullable();
            $table->dateTime('last_confirmed_at')->nullable();
            $table->unsignedBigInteger('first_confirmed_page_id')->nullable();

            $table->unsignedInteger('total_confirmed_orders')->default(0);
            $table->unsignedInteger('total_delivered_orders')->default(0);
            $table->decimal('total_confirmed_spend', 18, 2)->default(0);
            $table->decimal('total_delivered_spend', 18, 2)->default(0);

            $table->dateTime('customer_created_at')->nullable();

            $table->timestamps();

            $table->unique(['workspace_id', 'customer_id'], 'wcf_workspace_customer_unique');
            $table->index(['workspace_id', 'first_confirmed_at'], 'wcf_workspace_first_confirmed_idx');
            $table->index(['workspace_id', 'last_confirmed_at'], 'wcf_workspace_last_confirmed_idx');
            $table->index(['workspace_id', 'first_confirmed_page_id'], 'wcf_workspace_first_page_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_customer_facts');
    }
};
