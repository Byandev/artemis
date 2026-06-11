<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_insights', function (Blueprint $table) {
            $table->unsignedBigInteger('meta_ads_ad_id');
            $table->date('date');
            $table->unsignedBigInteger('meta_ads_account_id');
            $table->unsignedBigInteger('meta_ads_campaign_id')->nullable();
            $table->unsignedBigInteger('meta_ads_set_id')->nullable();

            // ── Delivery & Traffic ──────────────────────────────────
            $table->decimal('spend', 20, 4)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('link_clicks')->nullable();
            $table->unsignedBigInteger('outbound_clicks')->nullable();
            $table->unsignedBigInteger('estimated_ad_recallers')->nullable();
            $table->unsignedBigInteger('page_photo_views')->nullable();

            // ── Video ───────────────────────────────────────────────
            $table->unsignedBigInteger('video_3sec_views')->nullable();
            $table->unsignedBigInteger('video_thruplay_views')->nullable();
            $table->unsignedBigInteger('video_p25_views')->nullable();
            $table->unsignedBigInteger('video_p50_views')->nullable();
            $table->unsignedBigInteger('video_p75_views')->nullable();
            $table->unsignedBigInteger('video_p100_views')->nullable();

            // ── Engagement ──────────────────────────────────────────
            $table->unsignedBigInteger('page_engagement')->nullable();
            $table->decimal('page_engagement_value', 20, 4)->nullable();
            $table->unsignedBigInteger('page_likes')->nullable();
            $table->decimal('page_likes_value', 20, 4)->nullable();
            $table->decimal('photo_views_value', 20, 4)->nullable();
            $table->unsignedBigInteger('post_engagement')->nullable();
            $table->decimal('post_engagement_value', 20, 4)->nullable();
            $table->unsignedBigInteger('post_comments')->nullable();
            $table->decimal('post_comments_value', 20, 4)->nullable();
            $table->unsignedBigInteger('post_shares')->nullable();
            $table->decimal('post_shares_value', 20, 4)->nullable();
            $table->unsignedBigInteger('post_saves')->nullable();
            $table->decimal('post_saves_value', 20, 4)->nullable();
            $table->unsignedBigInteger('post_reactions')->nullable();
            $table->decimal('post_reactions_value', 20, 4)->nullable();

            // ── Messaging ───────────────────────────────────────────
            $table->unsignedBigInteger('messaging_first_replies')->nullable();
            $table->decimal('messaging_first_replies_value', 20, 4)->nullable();
            $table->unsignedBigInteger('messaging_conversations_started')->nullable();
            $table->decimal('messaging_conversations_started_value', 20, 4)->nullable();

            // ── Commerce & Leads ────────────────────────────────────
            $table->unsignedBigInteger('initiate_checkout')->nullable();
            $table->decimal('initiate_checkout_value', 20, 4)->nullable();
            $table->unsignedBigInteger('conversions')->nullable();
            $table->unsignedBigInteger('purchases')->nullable();
            $table->decimal('purchase_value', 20, 4)->nullable();
            $table->unsignedBigInteger('on_facebook_leads')->nullable();
            $table->decimal('on_facebook_leads_value', 20, 4)->nullable();
            $table->unsignedBigInteger('leads')->nullable();
            $table->decimal('lead_value', 20, 4)->nullable();

            $table->timestamps();

            $table->primary(['meta_ads_ad_id', 'date']);
            $table->index(['meta_ads_account_id', 'date']);
            $table->index(['meta_ads_campaign_id', 'date']);
            $table->index(['meta_ads_set_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_insights');
    }
};
