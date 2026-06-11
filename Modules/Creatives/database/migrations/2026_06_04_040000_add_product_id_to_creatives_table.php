<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('workspace_id');

            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index(['workspace_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['workspace_id', 'product_id']);
            $table->dropColumn('product_id');
        });
    }
};
