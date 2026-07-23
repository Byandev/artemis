<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the employee-profile columns that aren't already on `users`.
     *
     * name / email / password already exist (password covers password_hash),
     * so only the remaining employee fields are added here.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('reminder_time')->nullable()->after('is_super_admin');
            $table->unsignedInteger('current_streak')->default(0)->after('reminder_time');
            $table->unsignedInteger('longest_streak')->default(0)->after('current_streak');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_time',
                'current_streak',
                'longest_streak',
            ]);
        });
    }
};
