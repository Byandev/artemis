<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auto-assignment hands a delivery date's orders to one CSR rather than
     * rotating them through a pool, so the JSON list of ids collapses to a
     * single nullable id.
     *
     * A database that already carries a pool keeps its first member — the one
     * the old rotation dealt to first — and the rest are dropped.
     */
    public function up(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('rmo_settings', 'auto_assign_user_id')) {
                // The CSR every unassigned RMO row goes to, as a pancake_users
                // id (UUID). Null means nobody is set, so auto-assignment does
                // nothing even when switched on.
                $table->string('auto_assign_user_id')->nullable()->after('enable_auto_assign');
            }
        });

        if (! Schema::hasColumn('rmo_settings', 'auto_assign_user_ids')) {
            return;
        }

        $settings = DB::table('rmo_settings')
            ->whereNotNull('auto_assign_user_ids')
            ->get(['id', 'auto_assign_user_ids']);

        foreach ($settings as $setting) {
            $pool = json_decode($setting->auto_assign_user_ids, true);

            if (! is_array($pool) || $pool === []) {
                continue;
            }

            DB::table('rmo_settings')
                ->where('id', $setting->id)
                ->update(['auto_assign_user_id' => (string) reset($pool)]);
        }

        Schema::table('rmo_settings', function (Blueprint $table) {
            $table->dropColumn('auto_assign_user_ids');
        });
    }

    public function down(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('rmo_settings', 'auto_assign_user_ids')) {
                $table->json('auto_assign_user_ids')->nullable();
            }
        });

        // Back to a pool of one, which is what the old rotation would have made
        // of a single configured CSR anyway.
        $settings = DB::table('rmo_settings')
            ->whereNotNull('auto_assign_user_id')
            ->get(['id', 'auto_assign_user_id']);

        foreach ($settings as $setting) {
            DB::table('rmo_settings')
                ->where('id', $setting->id)
                ->update(['auto_assign_user_ids' => json_encode([(string) $setting->auto_assign_user_id])]);
        }

        Schema::table('rmo_settings', function (Blueprint $table) {
            if (Schema::hasColumn('rmo_settings', 'auto_assign_user_id')) {
                $table->dropColumn('auto_assign_user_id');
            }
        });
    }
};
