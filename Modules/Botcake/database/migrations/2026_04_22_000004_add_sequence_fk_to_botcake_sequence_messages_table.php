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
        Schema::table('botcake_sequence_messages', function (Blueprint $table) {
            $table->index('sequence_id', 'botcake_sequence_messages_sequence_id_idx');
            $table->foreign('sequence_id', 'botcake_sequence_messages_sequence_id_fk')
                ->references('id')
                ->on('botcake_sequences')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('botcake_sequence_messages', function (Blueprint $table) {
            $table->dropForeign('botcake_sequence_messages_sequence_id_fk');
            $table->dropIndex('botcake_sequence_messages_sequence_id_idx');
        });
    }
};
