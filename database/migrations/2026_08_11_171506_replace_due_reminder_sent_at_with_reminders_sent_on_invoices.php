<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which reminders have gone out, rather than merely whether one has.
     *
     * A single timestamp answered "was the payer reminded?", which was enough
     * when there was one reminder. With several — five days out, three days
     * out, then the due date — each has to be tracked separately or the first
     * one sent would silence the rest. Stores the day offsets already used,
     * e.g. [5, 3].
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('reminders_sent')->nullable()->after('paid_at');
            $table->dropColumn('due_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('due_reminder_sent_at')->nullable()->after('paid_at');
            $table->dropColumn('reminders_sent');
        });
    }
};
