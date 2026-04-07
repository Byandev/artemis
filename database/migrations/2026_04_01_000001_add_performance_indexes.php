<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->index(['workspace_id', 'confirmed_at', 'customer_id', 'status'], 'po_workspace_confirmed_customer_status');
            $table->index(['workspace_id', 'page_id'], 'po_workspace_page');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->index(['workspace_id', 'owner_id'], 'pages_workspace_owner');
            $table->index(['workspace_id', 'shop_id'], 'pages_workspace_shop');
        });
    }

    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropIndex('po_workspace_confirmed_customer_status');
            $table->dropIndex('po_workspace_page');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('pages_workspace_owner');
            $table->dropIndex('pages_workspace_shop');
        });
    }
};
