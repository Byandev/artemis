<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move connected third-party accounts off the users table.
     *
     * One row per user per service, holding a token and nothing else that could
     * be used to sign in as them. No username, no password — the password is
     * exchanged for the token at connect time and never written down, and the
     * account address is not kept either, since it is the username half of a
     * credential and the integration never needs it again.
     */
    public function up(): void
    {
        Schema::create('user_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The service slug, from App\Enums\IntegrationService.
            $table->string('service', 32);

            // Encrypted at rest. It cannot be hashed — the integration has to
            // send it back to the service verbatim.
            $table->text('token');

            // How the last unattended fetch went. Per integration rather than
            // per user, because a person may connect several and only one of
            // them be broken.
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();

            $table->timestamps();

            // Connecting the same service twice replaces the token rather than
            // leaving two rows to disagree about which one is live.
            $table->unique(['user_id', 'service']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['welle_email', 'welle_token', 'welle_last_synced_at', 'welle_last_error'],
                fn (string $column) => Schema::hasColumn('users', $column),
            )));
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'welle_email')) {
                $table->string('welle_email')->nullable()->after('gotyme_number');
            }

            if (! Schema::hasColumn('users', 'welle_token')) {
                $table->text('welle_token')->nullable()->after('welle_email');
            }

            if (! Schema::hasColumn('users', 'welle_last_synced_at')) {
                $table->timestamp('welle_last_synced_at')->nullable()->after('welle_token');
            }

            if (! Schema::hasColumn('users', 'welle_last_error')) {
                $table->string('welle_last_error')->nullable()->after('welle_last_synced_at');
            }
        });

        Schema::dropIfExists('user_integrations');
    }
};
