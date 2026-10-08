<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * auto_fill_address: new orders from this shop get their address read out
     * of the Messenger conversation and written back to Pancake. Off until
     * someone switches it on, which needs the shop's POS token.
     *
     * webhook_secret: created the first time auto-fill is switched on. Pancake
     * sends it back in the X-Artemis-Secret header of every order webhook.
     * Stored encrypted (text, since ciphertext outgrows a varchar).
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('auto_fill_address')->default(false);
            $table->text('webhook_secret')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['auto_fill_address', 'webhook_secret']);
        });
    }
};
