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
        Schema::table('workspaces', function (Blueprint $table) {
            // Credentials used by the n8n automation pipeline to authenticate
            // against the workspace's external ERP when fetching data.
            if (! Schema::hasColumn('workspaces', 'erp_email')) {
                $table->string('erp_email')->nullable()->after('public_password');
            }

            if (! Schema::hasColumn('workspaces', 'erp_password')) {
                // Stored encrypted (reversible) — n8n needs the plaintext to log in.
                $table->text('erp_password')->nullable()->after('erp_email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['erp_email', 'erp_password'],
                fn (string $column) => Schema::hasColumn('workspaces', $column),
            )));
        });
    }
};
