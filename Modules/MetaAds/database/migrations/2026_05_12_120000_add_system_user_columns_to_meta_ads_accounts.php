<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_accounts', function (Blueprint $table) {
            $table->boolean('uses_system_user')->default(false)->after('business_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_accounts', function (Blueprint $table) {
            $table->dropIndex(['uses_system_user']);
            $table->dropColumn('uses_system_user');
        });
    }
};
