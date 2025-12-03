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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('owner_id');
            $table->string('name');
            $table->text('facebook_url')->nullable();
            $table->text('pos_token')->nullable();
            $table->text('botcake_token')->nullable();
            $table->text('infotxt_token')->nullable();
            $table->string('infotxt_user_id')->nullable();
            $table->dateTime('orders_last_synced_at')->nullable();
             $table->timestamp('archived_at')->nullable()->after('orders_last_synced_at');
            $table->timestamps();

            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->onDelete('cascade');

            $table->foreign('shop_id')
                ->references('id')
                ->on('shops')
                ->onDelete('cascade');

            $table->foreign('owner_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
