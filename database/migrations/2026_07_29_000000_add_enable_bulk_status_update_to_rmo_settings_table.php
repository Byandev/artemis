<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            $table->boolean('enable_bulk_status_update')->default(false)->after('enable_edit_previous_day');
        });
    }

    public function down(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            $table->dropColumn('enable_bulk_status_update');
        });
    }
};
