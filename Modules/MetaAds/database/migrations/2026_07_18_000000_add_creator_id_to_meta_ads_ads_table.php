<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_ads', function (Blueprint $table) {
            // Internal creator (a workspace member). Meta doesn't provide this, so
            // it's assigned manually. Mirrors creatives.creator_id / accounts.owner_id.
            $table->foreignId('creator_id')->nullable()->after('created_by_meta_user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_ads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creator_id');
        });
    }
};
