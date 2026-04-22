<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_journey_notification_templates', function (Blueprint $table) {
            $table->boolean('is_enabled')->default(true)->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('parcel_journey_notification_templates', function (Blueprint $table) {
            $table->dropColumn('is_enabled');
        });
    }
};
