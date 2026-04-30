<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pancake_user_daily_engagements');
    }

    public function down(): void
    {
        //
    }
};
