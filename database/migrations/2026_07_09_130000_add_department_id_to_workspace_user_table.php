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
        Schema::table('workspace_user', function (Blueprint $table) {
            // A member's department is scoped to the workspace, so it lives on the
            // pivot rather than the users table. Clearing on delete keeps orphaned
            // assignments from lingering when a department is removed.
            $table->foreignId('department_id')
                ->nullable()
                ->after('role_id')
                ->constrained('departments')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspace_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
