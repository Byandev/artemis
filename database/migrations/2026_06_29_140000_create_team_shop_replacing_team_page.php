<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_shop', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['team_id', 'shop_id']);
            $table->index('shop_id');
        });

        // Carry over existing team-level ownership: a team that owned a page now
        // owns that page's shop.
        DB::statement(<<<'SQL'
            INSERT INTO team_shop (team_id, shop_id, created_at, updated_at)
            SELECT DISTINCT tp.team_id, p.shop_id, NOW(), NOW()
            FROM team_page tp
            JOIN pages p ON p.id = tp.page_id
            WHERE p.shop_id IS NOT NULL
        SQL);

        Schema::dropIfExists('team_page');
    }

    public function down(): void
    {
        Schema::create('team_page', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['team_id', 'page_id']);
            $table->index('page_id');
        });

        // Reverse: a team that owns a shop owns all of that shop's pages.
        DB::statement(<<<'SQL'
            INSERT INTO team_page (team_id, page_id, created_at, updated_at)
            SELECT DISTINCT ts.team_id, p.id, NOW(), NOW()
            FROM team_shop ts
            JOIN pages p ON p.shop_id = ts.shop_id
        SQL);

        Schema::dropIfExists('team_shop');
    }
};
