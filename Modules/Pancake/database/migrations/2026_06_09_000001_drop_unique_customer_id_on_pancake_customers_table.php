<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pancake_customers', function (Blueprint $table) {
            $table->dropUnique('pc_customer_id_uq');
        });
    }

    public function down(): void {}
};
