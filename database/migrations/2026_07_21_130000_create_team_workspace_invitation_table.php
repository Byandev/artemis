<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_workspace_invitation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_invitation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workspace_invitation_id', 'team_id'], 'invitation_team_unique');
        });

        // Carry over the single team each pending invitation may already have.
        DB::table('workspace_invitations')
            ->whereNotNull('team_id')
            ->orderBy('id')
            ->chunk(200, function ($invitations) {
                DB::table('team_workspace_invitation')->insert(
                    $invitations->map(fn ($invitation) => [
                        'workspace_invitation_id' => $invitation->id,
                        'team_id' => $invitation->team_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            });

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('role_id')
                ->constrained()->nullOnDelete();
        });

        // Keep the first team of each invitation; the rest cannot be represented.
        DB::table('team_workspace_invitation')
            ->orderBy('id')
            ->get()
            ->groupBy('workspace_invitation_id')
            ->each(function ($rows, $invitationId) {
                DB::table('workspace_invitations')
                    ->where('id', $invitationId)
                    ->update(['team_id' => $rows->first()->team_id]);
            });

        Schema::dropIfExists('team_workspace_invitation');
    }
};
