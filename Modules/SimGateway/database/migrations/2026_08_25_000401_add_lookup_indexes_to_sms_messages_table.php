<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The gateway callbacks look messages up by two unindexed paths:
     *
     * - delivery reports resolve `provider_message_id` ('yxgp:<tid>') on every
     *   push, once per report item;
     * - inbound pushes de-duplicate on (sim_id, from_number, message, sent_at).
     *
     * Neither had a usable index, so both full-scanned the message table.
     * `sim_id` alone is covered by its foreign key, but a SIM accumulates every
     * message it ever sent, so pairing it with `sent_at` is what actually keeps
     * the de-dup probe narrow.
     */
    private const INDEXES = [
        'idx_sim_gateway_sms_messages_provider_message_id' => ['provider_message_id'],
        'idx_sim_gateway_sms_messages_sim_sent_at' => ['sim_id', 'sent_at'],
    ];

    public function up(): void
    {
        $existing = $this->indexNames();

        Schema::table('sim_gateway_sms_messages', function (Blueprint $table) use ($existing) {
            foreach (self::INDEXES as $name => $columns) {
                if (! $existing->contains($name)) {
                    $table->index($columns, $name);
                }
            }
        });
    }

    public function down(): void
    {
        $existing = $this->indexNames();

        Schema::table('sim_gateway_sms_messages', function (Blueprint $table) use ($existing) {
            foreach (array_keys(self::INDEXES) as $name) {
                if ($existing->contains($name)) {
                    $table->dropIndex($name);
                }
            }
        });
    }

    private function indexNames(): Collection
    {
        return collect(Schema::getIndexes('sim_gateway_sms_messages'))->pluck('name');
    }
};
