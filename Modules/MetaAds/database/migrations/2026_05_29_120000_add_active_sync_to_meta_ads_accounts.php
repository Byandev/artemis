<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_accounts', function (Blueprint $table) {
            $table->boolean('active_sync')->default(true)->after('uses_system_user')->index();
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_accounts', function (Blueprint $table) {
            $table->dropIndex(['active_sync']);
            $table->dropColumn('active_sync');
        });
    }
};
