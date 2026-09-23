<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rdp_prompt_settings', function (Blueprint $table) {
            // Whether the render carries the product's own branding.
            //
            // On by default: the point of the step is to see the actual
            // product. Off draws blank packaging instead, which is the safer
            // output when label text comes back misspelled — image models are
            // still unreliable at lettering, so this stays switchable rather
            // than being decided once in code.
            $table->boolean('packshot_show_label')->default(true)->after('packshot_count');
        });
    }

    public function down(): void
    {
        Schema::table('rdp_prompt_settings', function (Blueprint $table) {
            $table->dropColumn('packshot_show_label');
        });
    }
};
