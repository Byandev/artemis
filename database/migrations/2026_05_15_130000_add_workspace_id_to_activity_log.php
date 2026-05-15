<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            // If the package migration hasn't run yet, skip — this migration
            // will run (and succeed) once the package migration creates the table.
            return;
        }

        Schema::table('activity_log', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_log', 'workspace_id')) {
                $table->unsignedBigInteger('workspace_id')->nullable()->after('causer_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        Schema::table('activity_log', function (Blueprint $table) {
            if (Schema::hasColumn('activity_log', 'workspace_id')) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                // drop index if exists; safer to catch any exceptions
                try {
                    $table->dropIndex(['workspace_id']);
                } catch (\Throwable $e) {
                }

                $table->dropColumn('workspace_id');
            }
        });
    }
};
