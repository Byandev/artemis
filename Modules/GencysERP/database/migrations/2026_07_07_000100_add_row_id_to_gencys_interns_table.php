<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_interns', function (Blueprint $table) {
            // Gencys' row id; the upsert key per workspace (null for manual rows).
            $table->unsignedBigInteger('row_id')->nullable()->after('workspace_id');

            $table->unique(['workspace_id', 'row_id']);
        });
    }

    public function down(): void
    {
        Schema::table('gencys_interns', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'row_id']);
            $table->dropColumn('row_id');
        });
    }
};
