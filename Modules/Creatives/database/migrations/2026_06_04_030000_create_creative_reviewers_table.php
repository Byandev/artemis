<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_reviewers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('creative_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('creative_id')->references('id')->on('creatives')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['creative_id', 'user_id']);
        });

        // Carry existing single assignments into the new pivot.
        DB::table('creatives')
            ->whereNotNull('assigned_reviewer_id')
            ->orderBy('id')
            ->each(function ($creative) {
                DB::table('creative_reviewers')->insertOrIgnore([
                    'creative_id' => $creative->id,
                    'user_id' => $creative->assigned_reviewer_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('creatives', function (Blueprint $table) {
            $table->dropForeign(['assigned_reviewer_id']);
            $table->dropIndex(['workspace_id', 'assigned_reviewer_id']);
            $table->dropColumn('assigned_reviewer_id');
        });
    }

    public function down(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_reviewer_id')->nullable()->after('creator_id');
            $table->foreign('assigned_reviewer_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['workspace_id', 'assigned_reviewer_id']);
        });

        // Restore the first assigned reviewer per creative.
        $first = DB::table('creative_reviewers')
            ->orderBy('id')
            ->get()
            ->groupBy('creative_id');

        foreach ($first as $creativeId => $rows) {
            DB::table('creatives')
                ->where('id', $creativeId)
                ->update(['assigned_reviewer_id' => $rows->first()->user_id]);
        }

        Schema::dropIfExists('creative_reviewers');
    }
};
