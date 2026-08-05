<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * People assigned to an ad account on Meta's side (Business Manager →
     * ad account → People). Distinct from `meta_ads_user_account`, which only
     * records the Facebook users who authorized Artemis.
     */
    public function up(): void
    {
        Schema::create('meta_ads_account_people', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meta_ads_account_id');
            $table->unsignedBigInteger('meta_user_id');
            $table->string('name')->nullable();
            // SYSTEM_USER / ADMIN_SYSTEM_USER when the assignee is a system user.
            $table->string('user_type')->nullable();
            // Raw Meta tasks: MANAGE / ADVERTISE / ANALYZE / DRAFT.
            $table->json('tasks')->nullable();
            // Human label derived from tasks at sync time (Admin/Advertiser/...).
            $table->string('role')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['meta_ads_account_id', 'meta_user_id'], 'meta_ads_account_people_unique');
            $table->index('meta_ads_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_account_people');
    }
};
