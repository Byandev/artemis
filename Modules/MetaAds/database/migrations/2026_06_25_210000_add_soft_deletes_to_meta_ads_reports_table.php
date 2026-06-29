<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_reports', function (Blueprint $table) {
            // Archive support: archived reports are soft-deleted (deleted_at set),
            // restorable, or permanently removed.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_reports', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
