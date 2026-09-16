<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Each user connects their own Welle account from
            // Settings → Integrations; the workspace-level
            // `welle_module_enabled` toggle decides whether the page exists.
            if (! Schema::hasColumn('users', 'welle_email')) {
                $table->string('welle_email')->nullable()->after('gotyme_number');
            }

            if (! Schema::hasColumn('users', 'welle_password')) {
                // Stored encrypted (reversible) — the integration needs the
                // plaintext to sign in to Welle on the user's behalf.
                $table->text('welle_password')->nullable()->after('welle_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['welle_email', 'welle_password'],
                fn (string $column) => Schema::hasColumn('users', $column),
            )));
        });
    }
};
