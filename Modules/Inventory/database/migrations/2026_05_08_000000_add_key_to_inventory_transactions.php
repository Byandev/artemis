<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->string('key')->nullable()->after('ref_no');
            $table->unique(['workspace_id', 'key'], 'inv_txn_workspace_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropUnique('inv_txn_workspace_key_unique');
            $table->dropColumn('key');
        });
    }
};