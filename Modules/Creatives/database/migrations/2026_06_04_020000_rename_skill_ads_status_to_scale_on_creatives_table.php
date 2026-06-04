<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Widen the enum to include both old and new values so existing rows can be remapped.
        DB::statement("ALTER TABLE creatives MODIFY ads_status ENUM('pending', 'running', 'kill', 'skill', 'scale') NOT NULL DEFAULT 'pending'");

        DB::table('creatives')->where('ads_status', 'skill')->update(['ads_status' => 'scale']);

        DB::statement("ALTER TABLE creatives MODIFY ads_status ENUM('pending', 'running', 'kill', 'scale') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE creatives MODIFY ads_status ENUM('pending', 'running', 'kill', 'skill', 'scale') NOT NULL DEFAULT 'pending'");

        DB::table('creatives')->where('ads_status', 'scale')->update(['ads_status' => 'skill']);

        DB::statement("ALTER TABLE creatives MODIFY ads_status ENUM('pending', 'running', 'kill', 'skill') NOT NULL DEFAULT 'pending'");
    }
};
