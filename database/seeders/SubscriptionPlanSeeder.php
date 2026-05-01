<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $plans = [
            [
                'code' => SubscriptionPlan::CODE_FREE_TRIAL,
                'name' => 'Free Trial',
                'price_php' => 0,
                'order_limit' => null,
                'page_limit' => 1,
                'data_retention_months' => 1,
                'analytics_tier' => SubscriptionPlan::ANALYTICS_BASIC,
                'parcel_journey_rate_php' => null,
                'parcel_journey_sms_enabled' => false,
                'support_tier' => SubscriptionPlan::SUPPORT_CHAT,
                'trial_days' => 30,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => SubscriptionPlan::CODE_STARTER,
                'name' => 'Starter',
                'price_php' => 1499,
                'order_limit' => 3000,
                'page_limit' => null,
                'data_retention_months' => 3,
                'analytics_tier' => SubscriptionPlan::ANALYTICS_BASIC,
                'parcel_journey_rate_php' => 0.50,
                'parcel_journey_sms_enabled' => false,
                'support_tier' => SubscriptionPlan::SUPPORT_CHAT,
                'trial_days' => null,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => SubscriptionPlan::CODE_GROWTH,
                'name' => 'Growth',
                'price_php' => 3999,
                'order_limit' => 10000,
                'page_limit' => null,
                'data_retention_months' => 6,
                'analytics_tier' => SubscriptionPlan::ANALYTICS_FULL,
                'parcel_journey_rate_php' => 0.35,
                'parcel_journey_sms_enabled' => true,
                'support_tier' => SubscriptionPlan::SUPPORT_PRIORITY_CHAT,
                'trial_days' => null,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'code' => SubscriptionPlan::CODE_SCALE,
                'name' => 'Scale',
                'price_php' => 9999,
                'order_limit' => 30000,
                'page_limit' => null,
                'data_retention_months' => 12,
                'analytics_tier' => SubscriptionPlan::ANALYTICS_FULL,
                'parcel_journey_rate_php' => 0.20,
                'parcel_journey_sms_enabled' => true,
                'support_tier' => SubscriptionPlan::SUPPORT_DEDICATED,
                'trial_days' => null,
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('subscription_plans')->updateOrInsert(
                ['code' => $plan['code']],
                [
                    ...$plan,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
