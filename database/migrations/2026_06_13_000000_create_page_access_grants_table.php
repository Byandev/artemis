<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user / per-team page-access grants. A grantee (User or Team) is limited
     * to the pages granted here; everything downstream (orders, RTS, dashboards)
     * filters by the resolved page set. No rows for a user (and none for any of
     * their teams) means unrestricted — they see everything.
     */
    public function up(): void
    {
        Schema::create('page_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('grantee_type'); // App\Models\User | App\Models\Team
            $table->unsignedBigInteger('grantee_id');
            $table->unsignedBigInteger('page_id');
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->unique(['workspace_id', 'grantee_type', 'grantee_id', 'page_id'], 'page_access_grants_unique');
            $table->index(['workspace_id', 'grantee_type', 'grantee_id'], 'page_access_grants_grantee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_access_grants');
    }
};
