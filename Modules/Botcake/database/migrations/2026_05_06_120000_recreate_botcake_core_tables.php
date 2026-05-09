<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop and recreate the three core Botcake tables (flows, sequences,
     * sequence_messages) so each one uses the external Botcake-supplied id
     * directly as its primary key. The FK-dependent daily-stat tables are
     * also recreated because: (a) their FKs reference the parent tables we
     * are dropping, and (b) sequence_message_daily_stats.sequence_message_id
     * pointed at an auto-increment PK that no longer exists.
     *
     * NOTE: this migration is destructive — all rows in the listed tables
     * are wiped. The next sync run (FetchFlows, FetchSequences,
     * FetchSequenceStatistics, FetchFlowStatistics) will repopulate them.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // Drop in reverse dependency order.
        Schema::dropIfExists('botcake_sequence_message_daily_stats');
        Schema::dropIfExists('botcake_sequence_messages');
        Schema::dropIfExists('botcake_sequence_daily_stats');
        Schema::dropIfExists('botcake_sequences');
        Schema::dropIfExists('botcake_flow_daily_stats');
        Schema::dropIfExists('botcake_flows');

        // ---- flows ----
        Schema::create('botcake_flows', function (Blueprint $table) {
            // External Botcake flow id used directly as PK.
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_removed')->default(false);
            $table->string('name');
            $table->unsignedBigInteger('delivery')->default(0);
            $table->unsignedBigInteger('is_clicked')->default(0);
            $table->unsignedBigInteger('seen')->default(0);
            $table->unsignedBigInteger('sent')->default(0);
            $table->unsignedBigInteger('total_phone_number')->default(0);
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->unique(['page_id', 'id']);
        });

        Schema::create('botcake_flow_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('botcake_flows')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('delivery')->default(0);
            $table->unsignedBigInteger('is_clicked')->default(0);
            $table->unsignedBigInteger('seen')->default(0);
            $table->unsignedBigInteger('sent')->default(0);
            $table->unsignedBigInteger('total_phone_number')->default(0);
            $table->timestamps();

            $table->unique(['flow_id', 'date']);
            $table->index('date');
        });

        // ---- sequences ----
        Schema::create('botcake_sequences', function (Blueprint $table) {
            // External Botcake sequence id used directly as PK.
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('page_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->unique(['page_id', 'id']);
        });

        Schema::create('botcake_sequence_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('botcake_sequences')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('delivery')->default(0);
            $table->unsignedBigInteger('seen')->default(0);
            $table->unsignedBigInteger('sent')->default(0);
            $table->unsignedBigInteger('total_phone_number')->default(0);
            $table->timestamps();

            $table->unique(['sequence_id', 'date']);
            $table->index('date');
        });

        // ---- sequence_messages ----
        Schema::create('botcake_sequence_messages', function (Blueprint $table) {
            // External Botcake message id used directly as PK — the previous
            // schema kept a separate auto-increment id alongside a message_id
            // column; this collapses the two.
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('sequence_id');
            $table->string('name');
            $table->unsignedBigInteger('delivery')->default(0);
            $table->unsignedBigInteger('is_clicked')->default(0);
            $table->unsignedBigInteger('seen')->default(0);
            $table->unsignedBigInteger('sent')->default(0);
            $table->unsignedBigInteger('total_phone_number')->default(0);
            $table->timestamps();

            $table->foreign('sequence_id', 'botcake_sequence_messages_sequence_id_fk')
                ->references('id')
                ->on('botcake_sequences')
                ->cascadeOnDelete();
            $table->index('sequence_id', 'botcake_sequence_messages_sequence_id_idx');
        });

        Schema::create('botcake_sequence_message_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sequence_message_id');
            $table->date('date');
            $table->unsignedBigInteger('delivery')->default(0);
            $table->unsignedBigInteger('seen')->default(0);
            $table->unsignedBigInteger('sent')->default(0);
            $table->unsignedBigInteger('total_phone_number')->default(0);
            $table->timestamps();

            $table->foreign('sequence_message_id')
                ->references('id')
                ->on('botcake_sequence_messages')
                ->cascadeOnDelete();
            $table->unique(['sequence_message_id', 'date'], 'botcake_seq_msg_daily_unique');
            $table->index('date', 'botcake_seq_msg_daily_date_idx');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * No-op: rolling back this migration would require restoring the
     * pre-existing schema, which is owned by the 2026_02_11_* and
     * 2026_04_22_* migrations. Re-running them after rollback would
     * recreate the original tables.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('botcake_sequence_message_daily_stats');
        Schema::dropIfExists('botcake_sequence_messages');
        Schema::dropIfExists('botcake_sequence_daily_stats');
        Schema::dropIfExists('botcake_sequences');
        Schema::dropIfExists('botcake_flow_daily_stats');
        Schema::dropIfExists('botcake_flows');
        Schema::enableForeignKeyConstraints();
    }
};
