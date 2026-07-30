<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            $table->boolean('enable_auto_tag_status')->default(false)->after('enable_bulk_status_update');
            // {parcel_status => rmo_status}, e.g. {"delivered": "DELIVERED"}.
            $table->json('auto_tag_status_map')->nullable()->after('enable_auto_tag_status');
        });
    }

    public function down(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            $table->dropColumn(['enable_auto_tag_status', 'auto_tag_status_map']);
        });
    }
};
