<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_pages', function (Blueprint $table) {
            $table->string('page_url')->nullable()->after('platform');
            $table->string('fb_page_id')->nullable()->after('page_url');
            // NOT NULL, but the table already has rows — default '' so the add succeeds.
            $table->string('shop_id')->default('')->after('fb_page_id');
            $table->string('pos_token', 1024)->default('')->after('shop_id');
        });
    }

    public function down(): void
    {
        Schema::table('gencys_pages', function (Blueprint $table) {
            $table->dropColumn(['page_url', 'fb_page_id', 'shop_id', 'pos_token']);
        });
    }
};
