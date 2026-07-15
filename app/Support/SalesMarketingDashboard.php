<?php

namespace App\Support;

use App\Models\Workspace;

/**
 * Single source of truth for the Sales & Marketing dashboard's tabs. Each tab
 * is its own URL (route path); the controllers that render a tab reuse this so
 * the tab bar is identical no matter which tab is active.
 */
final class SalesMarketingDashboard
{
    /**
     * @return array<int, array{key: string, label: string, url: string}>
     */
    public static function tabs(Workspace $workspace): array
    {
        $base = "/workspaces/{$workspace->slug}/sales-marketing/dashboard";

        return [
            ['key' => 'daily-report', 'label' => 'Daily Report', 'url' => $base],
            ['key' => 'page-roas-tracker', 'label' => 'Page ROAS Tracker', 'url' => "{$base}/page-roas-tracker"],
            ['key' => 'ad-spend-goals', 'label' => 'Ad Spend Goals', 'url' => "{$base}/ad-spend-goals"],
            ['key' => 'ad-spent-summary', 'label' => 'Ad Spent Summary', 'url' => "{$base}/ad-spent-summary"],
        ];
    }
}
