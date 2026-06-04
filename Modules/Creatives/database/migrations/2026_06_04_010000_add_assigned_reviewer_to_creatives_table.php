<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_reviewer_id')->nullable()->after('creator_id');

            $table->foreign('assigned_reviewer_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['workspace_id', 'assigned_reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            $table->dropForeign(['assigned_reviewer_id']);
            $table->dropIndex(['workspace_id', 'assigned_reviewer_id']);
            $table->dropColumn('assigned_reviewer_id');
        });
    }
};
