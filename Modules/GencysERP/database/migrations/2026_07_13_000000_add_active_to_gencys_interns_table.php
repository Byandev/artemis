<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_interns', function (Blueprint $table) {
            $table->boolean('active')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('gencys_interns', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
