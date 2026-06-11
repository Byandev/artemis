<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_creatives', function (Blueprint $table) {
            $table->unsignedBigInteger('meta_page_id')->nullable()->after('meta_ads_account_id');
            $table->index('meta_page_id');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_creatives', function (Blueprint $table) {
            $table->dropIndex(['meta_page_id']);
            $table->dropColumn('meta_page_id');
        });
    }
};
