<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert call_logs.user_id to a UUID (char(36)). Some environments
        // still have this column as an integer due to earlier schema drift;
        // MySQL can MODIFY the column in place without dropping the indexes
        // it participates in. Kept nullable so the conversion is non-destructive
        // for any existing rows (the API already requires a UUID on write).
        Schema::table('call_logs', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }
};
