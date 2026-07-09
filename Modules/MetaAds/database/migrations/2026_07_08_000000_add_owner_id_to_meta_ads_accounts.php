<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_accounts', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('active_sync')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
