<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $exists = collect(Schema::getIndexes('roles'))
            ->pluck('name')
            ->contains('roles_role_unique');

        if ($exists) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique('roles_role_unique');
            });
        }
    }

    public function down(): void
    {
        //
    }
};
