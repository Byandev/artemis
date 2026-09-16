<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Credentials are per user and the fetch runs unattended, so a
            // password changed on Welle's side would otherwise just stop
            // producing data with nobody the wiser. Settings → Integrations
            // reads these two to say so.
            if (! Schema::hasColumn('users', 'welle_last_synced_at')) {
                $table->timestamp('welle_last_synced_at')->nullable()->after('welle_password');
            }

            if (! Schema::hasColumn('users', 'welle_last_error')) {
                $table->string('welle_last_error')->nullable()->after('welle_last_synced_at');
            }

            // Welle works these out itself and sends them with every progress
            // week, so they are stored rather than recounted from the daily
            // rows — which cannot see past the weeks we have fetched anyway.
            if (! Schema::hasColumn('users', 'welle_streak_days')) {
                $table->unsignedSmallInteger('welle_streak_days')->nullable()->after('welle_last_error');
            }

            if (! Schema::hasColumn('users', 'welle_longest_streak')) {
                $table->unsignedSmallInteger('welle_longest_streak')->nullable()->after('welle_streak_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['welle_last_synced_at', 'welle_last_error', 'welle_streak_days', 'welle_longest_streak'],
                fn (string $column) => Schema::hasColumn('users', $column),
            )));
        });
    }
};
