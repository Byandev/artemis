<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_interns', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('workspace_id')
                ->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('gencys_interns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('is_active');
        });
    }
};
