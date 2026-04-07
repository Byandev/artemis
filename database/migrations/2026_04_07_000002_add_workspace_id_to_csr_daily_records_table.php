<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('csr_daily_records', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable()->after('id');

            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->cascadeOnDelete();

            $table->index(['workspace_id', 'csr_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('csr_daily_records', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropIndex(['workspace_id', 'csr_id', 'date']);
            $table->dropColumn('workspace_id');
        });
    }
};