<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How this form is packaged, in words the image generator can draw from.
     *
     * Previously a hardcoded map inside ProductResearchPackshotGenerator, which meant a
     * form the workspace added itself got a vague fallback and nobody could
     * correct one that rendered wrong. The form is the right place for it: it
     * is the thing that knows what it physically is.
     */
    public function up(): void
    {
        Schema::table('product_forms', function (Blueprint $table) {
            $table->text('packshot_description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('product_forms', function (Blueprint $table) {
            $table->dropColumn('packshot_description');
        });
    }
};
