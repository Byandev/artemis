<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Just a shorter, general name for the Creatives Tracker app's push token table. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('creatives_tracker_push_tokens', 'device_tokens');
    }

    public function down(): void
    {
        Schema::rename('device_tokens', 'creatives_tracker_push_tokens');
    }
};
