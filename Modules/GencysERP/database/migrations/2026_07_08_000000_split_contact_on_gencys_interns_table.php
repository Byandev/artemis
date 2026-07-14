<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gencys_interns', function (Blueprint $table) {
            $table->string('contact_number')->nullable()->after('username');
            $table->string('email')->nullable()->after('contact_number');
            $table->dropColumn('contact_number_email');
        });
    }

    public function down(): void
    {
        Schema::table('gencys_interns', function (Blueprint $table) {
            $table->string('contact_number_email')->nullable()->after('username');
            $table->dropColumn(['contact_number', 'email']);
        });
    }
};
