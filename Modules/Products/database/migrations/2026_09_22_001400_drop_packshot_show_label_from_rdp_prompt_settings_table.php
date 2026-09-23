<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The label is governed by the image prompt itself — its default text asks
     * for "a printed label carrying the name" — so a separate toggle was a
     * second control for one decision, and the Configure prompt dialog has no
     * room for it in the design.
     */
    public function up(): void
    {
        Schema::table('rdp_prompt_settings', function (Blueprint $table) {
            $table->dropColumn('packshot_show_label');
        });
    }

    public function down(): void
    {
        Schema::table('rdp_prompt_settings', function (Blueprint $table) {
            $table->boolean('packshot_show_label')->default(true)->after('packshot_count');
        });
    }
};
