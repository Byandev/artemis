<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creatives_reviews', function (Blueprint $table) {
            // The moment in a video creative the review is about. Null for a
            // review of the creative as a whole (and for every image creative).
            $table->decimal('timestamp_seconds', 10, 3)->nullable()->after('feedback');
        });
    }

    public function down(): void
    {
        Schema::table('creatives_reviews', function (Blueprint $table) {
            $table->dropColumn('timestamp_seconds');
        });
    }
};
