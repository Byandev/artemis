<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Connected third-party accounts, kept off the users table.
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
    }

    public function down(): void
    {
        Schema::dropIfExists('user_integrations');
    }
};
