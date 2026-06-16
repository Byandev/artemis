<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user, per-ad-account access grants within a workspace.
     *
     * A member with no rows here is unrestricted (sees/acts on every account
     * their role allows). Once at least one row exists, the member is limited to
     * the listed accounts — `view` is read-only, `manage` additionally allows
     * creating/editing optimization rules that include the account and
     * approving/rejecting its proposals.
     */
    public function up(): void
    {
        Schema::create('meta_ads_account_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id');
            $table->foreignId('user_id');
            $table->unsignedBigInteger('meta_ads_account_id');
            $table->enum('access_level', ['view', 'manage']);
            $table->timestamps();

            // Explicit short names — auto-generated identifiers exceed MySQL's
            // 64-character limit.
            $table->unique(['workspace_id', 'user_id', 'meta_ads_account_id'], 'maaa_ws_user_account_unique');

            $table->foreign('workspace_id', 'maaa_workspace_id_foreign')
                ->references('id')->on('workspaces')->onDelete('cascade');
            $table->foreign('user_id', 'maaa_user_id_foreign')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('meta_ads_account_id', 'maaa_account_id_foreign')
                ->references('id')->on('meta_ads_accounts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_account_access');
    }
};
