<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A manual row names its product as text, the same way it names its ad
     * account and page. product_id stays for synced rows, which resolve a real
     * product through page -> shop; the two never both apply to one row.
     */
    public function up(): void
    {
        Schema::table('meta_ads_testing_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_testing_items', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });
    }
};
