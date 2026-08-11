<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a workspace's invoices should go. Nullable on purpose — most
     * workspaces bill to whoever owns them, and only the ones with a separate
     * accounts inbox need to say so.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('billing_email')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('billing_email');
        });
    }
};
