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
        Schema::table('pages', function (Blueprint $table) {
            // Which SMS provider this page sends parcel-journey texts through.
            // Existing pages keep using InfoTxt.
            $table->string('sms_provider')->default('infotxt')->after('infotxt_user_id');
            $table->text('sendgate_api_key')->nullable()->after('sms_provider');
            $table->string('sendgate_sim_id')->nullable()->after('sendgate_api_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['sms_provider', 'sendgate_api_key', 'sendgate_sim_id']);
        });
    }
};
