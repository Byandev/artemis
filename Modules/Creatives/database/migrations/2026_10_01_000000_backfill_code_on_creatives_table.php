<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Creatives\Models\Creative;

return new class extends Migration
{
    /**
     * Give creatives made before the code column existed their own code.
     * New creatives already get one from Creative::booted().
     */
    public function up(): void
    {
        DB::table('creatives')
            ->whereNull('code')
            ->select('id')
            ->chunkById(500, function ($creatives) {
                foreach ($creatives as $creative) {
                    DB::table('creatives')
                        ->where('id', $creative->id)
                        ->update(['code' => Creative::generateCode()]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible: codes may already be shared, and nothing records
        // which rows were backfilled versus created with a code.
    }
};
