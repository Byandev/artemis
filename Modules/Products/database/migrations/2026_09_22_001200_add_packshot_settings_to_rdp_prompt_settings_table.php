<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rdp_prompt_settings', function (Blueprint $table) {
            // The packshot step has its own Configure prompt dialog, so it gets
            // its own pair rather than sharing the naming one — the two ask for
            // very different things.
            $table->text('packshot_prompt')->nullable()->after('name_count');
            $table->unsignedTinyInteger('packshot_count')->default(5)->after('packshot_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('rdp_prompt_settings', function (Blueprint $table) {
            $table->dropColumn(['packshot_prompt', 'packshot_count']);
        });
    }
};
