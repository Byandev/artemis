<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_purchased_orders')) {
            return;
        }

        Schema::table('inventory_purchased_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_purchased_orders', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_purchased_orders')) {
            return;
        }

        Schema::table('inventory_purchased_orders', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_purchased_orders', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
