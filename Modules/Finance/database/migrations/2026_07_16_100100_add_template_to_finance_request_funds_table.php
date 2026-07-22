<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_request_funds', function (Blueprint $table) {
            $table->string('template')->default('blank')->after('workspace_id');
            // Snapshot of the requester's number at submission time: the profile
            // value can change later, but a submitted request must keep the
            // number the money was actually sent to.
            $table->string('gotyme_number')->nullable()->after('charge_to');

            $table->index(['workspace_id', 'template']);
        });
    }

    public function down(): void
    {
        Schema::table('finance_request_funds', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'template']);
            $table->dropColumn(['template', 'gotyme_number']);
        });
    }
};
