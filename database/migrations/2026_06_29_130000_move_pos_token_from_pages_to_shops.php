<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->text('pos_token')->nullable()->after('avatar_url');
        });

        // Backfill each shop's token from any of its pages that has one.
        DB::statement(<<<'SQL'
            UPDATE shops s
            SET pos_token = (
                SELECT p.pos_token
                FROM pages p
                WHERE p.shop_id = s.id
                  AND p.pos_token IS NOT NULL
                ORDER BY p.id
                LIMIT 1
            )
        SQL);

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('pos_token');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->text('pos_token')->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE pages p
            JOIN shops s ON s.id = p.shop_id
            SET p.pos_token = s.pos_token
        SQL);

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('pos_token');
        });
    }
};
