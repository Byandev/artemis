<?php

use App\Models\Page;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Models\OptimizationRuleLog;

/**
 * Optimization history carries no account or page column — both are resolved
 * through the logged target (campaign / ad set), so the filters are exercised
 * against real campaigns/ad sets rather than columns on the log itself.
 *
 * Two accounts, each with one campaign + one ad set, each on its own page:
 *   account 801 -> campaign 8100 / ad set 8101 -> Alpha Page (8001)
 *   account 802 -> campaign 8200 / ad set 8201 -> Bravo Page (8002)
 */
function seedLogHistory($workspace): array
{
    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Rule A',
        'target_type' => 'campaign',
        'action' => 'pause',
        'execution_mode' => 'approval',
    ]);

    AdAccount::create(['id' => 801, 'name' => 'Account One']);
    AdAccount::create(['id' => 802, 'name' => 'Account Two']);

    $alpha = Page::factory()->forWorkspace($workspace)->create(['id' => 8001, 'name' => 'Alpha Page']);
    $bravo = Page::factory()->forWorkspace($workspace)->create(['id' => 8002, 'name' => 'Bravo Page']);

    $build = function (int $account, int $base, int $pageId) {
        Campaign::create([
            'id' => $base,
            'meta_ads_account_id' => $account,
            'name' => 'Campaign '.$base,
        ]);
        AdSet::create([
            'id' => $base + 1,
            'meta_ads_account_id' => $account,
            'meta_ads_campaign_id' => $base,
            'meta_page_id' => $pageId,
            'name' => 'Ad Set '.$base,
        ]);
    };

    $build(801, 8100, $alpha->id);
    $build(802, 8200, $bravo->id);

    $log = function (string $targetType, int $targetId, string $action) use ($workspace, $rule) {
        OptimizationRuleLog::create([
            'workspace_id' => $workspace->id,
            'meta_ads_optimization_rule_id' => $rule->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action_taken' => $action,
            'conditions_snapshot' => [],
            'triggered_at' => now(),
        ]);
    };

    $log('campaign', 8100, 'pause');          // L1 — account 801 / Alpha
    $log('ad_set', 8101, 'increase_budget');  // L2 — account 801 / Alpha
    $log('campaign', 8200, 'enable');         // L3 — account 802 / Bravo

    return ['rule' => $rule, 'alpha' => $alpha, 'bravo' => $bravo];
}

function logsUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.optimization-rules.logs', ['workspace' => $workspace, ...$query]);
}

it('lists history with account + page filter options drawn from logged targets', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedLogHistory($workspace);

    $this->get(logsUrl($workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/integrations/meta-ads/optimization-rules/logs')
            ->has('logs.data', 3)
            ->has('rules', 1)
            ->has('adAccounts', 2)
            ->has('pages', 2)
            ->where('adAccounts.0.name', 'Account One')
            ->where('pages.0.name', 'Alpha Page')
        );
});

it('filters history by ad account', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedLogHistory($workspace);

    $this->get(logsUrl($workspace, ['ad_account_id' => ['801']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 2) // L1 (campaign) + L2 (ad set) on account 801
            ->where('query.accountIds', ['801'])
        );
});

it('filters history by page, matching a campaign through its ad sets', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedLogHistory($workspace);

    $this->get(logsUrl($workspace, ['page_id' => ['8002']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1) // L3 — campaign 8200, on Bravo via its ad set
            ->where('logs.data.0.target_id', 8200)
            ->where('query.pageIds', ['8002'])
        );
});

it('combines the account, page and action filters', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedLogHistory($workspace);

    $this->get(logsUrl($workspace, [
        'ad_account_id' => ['801'],
        'page_id' => ['8001'],
        'action' => ['increase_budget'],
    ]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1) // L2 only
            ->where('logs.data.0.target_id', 8101)
        );
});
