<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creatives_reviews', function (Blueprint $table) {
            // The area of an image creative the review points at, as
            // {x, y, w, h} fractions of the image (0–1) so it lands in the same
            // place at any display size. w = h = 0 is a single point. Null for
            // a review of the creative as a whole.
            $table->json('region')->nullable()->after('timestamp_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('creatives_reviews', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
