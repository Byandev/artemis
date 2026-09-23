<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rdp_prompt_settings', function (Blueprint $table) {
            $table->id();
            // One row per workspace, created the first time someone presses
            // Done in the Configure prompt dialog. Everyone else reads the
            // defaults on the model.
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();

            // The editable half of the system prompt — the brand's voice and
            // its constraints. Null means "still on the default", which is what
            // "Reset to default" puts it back to.
            $table->text('naming_prompt')->nullable();

            $table->unsignedTinyInteger('name_count')->default(10);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rdp_prompt_settings');
    }
};
