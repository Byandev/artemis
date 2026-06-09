<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creatives_reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewer_id')->nullable()->after('creative_id');
            $table->foreign('reviewer_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('creatives_reviews', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->dropColumn('reviewer_id');
        });
    }
};
