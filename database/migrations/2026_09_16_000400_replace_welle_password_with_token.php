<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stop keeping Welle passwords.
     *
     * The password is now used once, at connect time, to exchange for a Welle
     * API token — and then dropped. What sits at rest is a credential that
     * Welle can revoke on its own, that grants only what the integration reads,
     * and that is worth far less to anyone who gets hold of the database than a
     * password the person probably reuses elsewhere.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'welle_token')) {
                // Encrypted at rest, like the password it replaces — the
                // integration has to send it verbatim, so it cannot be hashed.
                $table->text('welle_token')->nullable()->after('welle_email');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'welle_password')) {
                $table->dropColumn('welle_password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'welle_password')) {
                $table->text('welle_password')->nullable()->after('welle_email');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'welle_token')) {
                $table->dropColumn('welle_token');
            }
        });
    }
};
