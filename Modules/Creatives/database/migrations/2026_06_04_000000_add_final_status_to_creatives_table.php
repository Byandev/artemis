<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            $table->enum('final_status', ['for_approval', 'approved', 'for_revision'])
                ->default('for_approval')
                ->after('ads_remarks');
            $table->timestamp('approved_at')->nullable()->after('final_status');
        });
    }

    public function down(): void
    {
        Schema::table('creatives', function (Blueprint $table) {
            $table->dropColumn(['final_status', 'approved_at']);
        });
    }
};
