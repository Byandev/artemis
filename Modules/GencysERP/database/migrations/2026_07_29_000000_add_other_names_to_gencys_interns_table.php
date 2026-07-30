<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_interns', function (Blueprint $table) {
            // Alternate spellings/aliases of this intern as they appear in the ERP
            // "intern & brand" cell, so orders resolve even when middle names differ.
            $table->json('other_names')->nullable()->after('username');
        });
    }

    public function down(): void
    {
        Schema::table('gencys_interns', function (Blueprint $table) {
            $table->dropColumn('other_names');
        });
    }
};
