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
        $exists = collect(Schema::getIndexes('products'))
            ->pluck('name')
            ->contains('products_workspace_id_code_unique');

        if ($exists) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique('products_workspace_id_code_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unique(['workspace_id', 'code']);
        });
    }
};
