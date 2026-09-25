<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            // Nullable: only creatives created from now on get a code. MySQL
            // unique indexes allow multiple NULLs, so older rows are fine.
            $table->char('code', 10)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
