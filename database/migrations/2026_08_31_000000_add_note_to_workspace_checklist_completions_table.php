<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workspace_checklist_completions', function (Blueprint $table) {
            // Optional context typed alongside the proof file when an item is
            // checked off. The file itself lives in the media library.
            $table->text('note')->nullable()->after('checked_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspace_checklist_completions', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
