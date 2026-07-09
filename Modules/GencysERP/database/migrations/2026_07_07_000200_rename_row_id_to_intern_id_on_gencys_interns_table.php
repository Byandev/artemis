<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_interns', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'row_id']);
            $table->renameColumn('row_id', 'intern_id');
        });

        Schema::table('gencys_interns', function (Blueprint $table) {
            $table->unique(['workspace_id', 'intern_id']);
        });
    }

    public function down(): void
    {
        Schema::table('gencys_interns', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'intern_id']);
            $table->renameColumn('intern_id', 'row_id');
        });

        Schema::table('gencys_interns', function (Blueprint $table) {
            $table->unique(['workspace_id', 'row_id']);
        });
    }
};
