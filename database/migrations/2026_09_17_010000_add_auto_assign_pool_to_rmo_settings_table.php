<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            // Guarded rather than added outright: a development database may
            // already carry an `enable_auto_assign` from an earlier single-CSR
            // take on this feature, and adding it twice fails the migration.
            if (! Schema::hasColumn('rmo_settings', 'enable_auto_assign')) {
                // Off by default: an upgraded workspace should not start handing
                // its orders out to CSRs on its own.
                $table->boolean('enable_auto_assign')->default(false)->after('enable_auto_tag_status');
            }

            if (! Schema::hasColumn('rmo_settings', 'auto_assign_user_ids')) {
                // The pool auto-assignment rotates through, as pancake_users ids
                // (UUIDs), e.g. ["9c1f…", "9d2a…"]. Empty or null means nobody is
                // eligible, so auto-assignment does nothing even when switched on.
                $table->json('auto_assign_user_ids')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rmo_settings', function (Blueprint $table) {
            foreach (['enable_auto_assign', 'auto_assign_user_ids'] as $column) {
                if (Schema::hasColumn('rmo_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
