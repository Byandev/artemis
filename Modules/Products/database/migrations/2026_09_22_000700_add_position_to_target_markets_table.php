<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('target_markets', function (Blueprint $table) {
            // A curated taxonomy does not read alphabetically — the seeded
            // categories run Cardiovascular, Metabolic & Endocrine,
            // Respiratory, which is the order a clinician groups them in, not
            // the order MySQL sorts them in. Ordering is therefore stored
            // rather than derived, the same way ProductFormVariant does it.
            $table->unsignedInteger('position')->default(0)->after('name');
        });

        // Backfill by id, so anything already seeded keeps the order it was
        // inserted in rather than collapsing to a single position.
        foreach (DB::table('target_markets')->orderBy('id')->get(['id', 'workspace_id', 'parent_id']) as $row) {
            $key = $row->workspace_id.':'.($row->parent_id ?? 'root');
            $next[$key] = ($next[$key] ?? -1) + 1;

            DB::table('target_markets')->where('id', $row->id)->update(['position' => $next[$key]]);
        }
    }

    public function down(): void
    {
        Schema::table('target_markets', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
