<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $blueprint) {
            // Adds the nullable 'deleted_at' timestamp column
            $blueprint->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $blueprint) {
            $blueprint->dropSoftDeletes();
        });
    }
};