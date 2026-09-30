<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // Admin-maintained client contact details, shown on /admin/workspaces.
            $table->string('messenger_link')->nullable()->after('max_shops');
            $table->timestamp('last_interaction_at')->nullable()->after('messenger_link');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['messenger_link', 'last_interaction_at']);
        });
    }
};
