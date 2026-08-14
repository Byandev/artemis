<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the due-date reminder went out.
     *
     * The reminder fires on the due date and only then, but the command runs
     * daily and can be run again by hand — so what stops a customer being
     * chased twice is this column, not the calendar.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('due_reminder_sent_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('due_reminder_sent_at');
        });
    }
};
