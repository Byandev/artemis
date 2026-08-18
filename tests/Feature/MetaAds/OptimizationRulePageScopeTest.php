<?php

use App\Models\Page;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Models\OptimizationRuleCondition;
use Modules\MetaAds\Models\User as MetaUser;
use Modules\MetaAds\Services\OptimizationRuleEvaluator;

/**
 * A rule can be narrowed to specific Facebook pages. Ad sets carry meta_page_id
 * directly; a campaign counts as being on a page when any of its ad sets
 * promotes it. An empty page list means every page.
 */
function seedPageScope($workspace): array
{
    // The account only counts as the workspace's once a connected meta user
    // links it — that's what AdAccount::forWorkspace() walks.
    $metaUser = MetaUser::create(['id' => 7300, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 720, 'name' => 'Acct']);
    $metaUser->adAccounts()->attach($account->id);

    $alpha = Page::factory()->forWorkspace($workspace)->create(['id' => 7401, 'name' => 'Alpha Page']);
    $bravo = Page::factory()->forWorkspace($workspace)->create(['id' => 7402, 'name' => 'Bravo Page']);

    // One campaign + ad set per page, both with a budget so a budget condition matches.
    $build = function (int $base, int $pageId) use ($account) {
        $campaign = Campaign::create([
            'id' => $base,
            'meta_ads_account_id' => $account->id,
            'name' => 'Campaign '.$base,
            // Needed for the budget condition when the rule targets campaigns.
            'daily_budget' => 100,
        ]);
        AdSet::create([
            'id' => $base + 1,
            'meta_ads_account_id' => $account->id,
            'meta_ads_campaign_id' => $campaign->id,
            'meta_page_id' => $pageId,
            'name' => 'Ad Set '.$base,
            'daily_budget' => 100,
        ]);

        return $campaign;
    };

    $build(7500, $alpha->id);
    $build(7600, $bravo->id);

    return ['account' => $account, 'alpha' => $alpha, 'bravo' => $bravo];
}

function makePageScopedRule($workspace, string $targetType, array $pageIds = []): OptimizationRule
{
    $rule = OptimizationRule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Scoped',
        'target_type' => $targetType,
        'action' => 'pause',
        'condition_operator' => 'and',
        'execution_mode' => 'approval',
        'is_active' => true,
        'frequency' => 'hourly',
    ]);
    OptimizationRuleCondition::create([
        'meta_ads_optimization_rule_id' => $rule->id,
        'metric' => 'budget',
        'operator' => '<=',
        'value' => 1000,
        'time_window' => 'today',
    ]);
    $rule->pages()->sync($pageIds);

    return $rule->load(['conditions', 'pages']);
}

it('evaluates every page when no page is selected', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedPageScope($workspace);

    $rule = makePageScopedRule($workspace, 'ad_set');
    $proposals = (new OptimizationRuleEvaluator)->plan($rule, $ctx['account']);

    expect($proposals)->toHaveCount(2);
});

it('narrows ad set targets to the selected pages', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedPageScope($workspace);

    $rule = makePageScopedRule($workspace, 'ad_set', [$ctx['alpha']->id]);
    $proposals = (new OptimizationRuleEvaluator)->plan($rule, $ctx['account']);

    expect($proposals)->toHaveCount(1)
        ->and((string) $proposals[0]['target_id'])->toBe('7501');
});

it('narrows campaign targets through their ad sets pages', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedPageScope($workspace);

    // A campaign has no page of its own — it qualifies via its ad sets.
    $rule = makePageScopedRule($workspace, 'campaign', [$ctx['bravo']->id]);
    $proposals = (new OptimizationRuleEvaluator)->plan($rule, $ctx['account']);

    expect($proposals)->toHaveCount(1)
        ->and((string) $proposals[0]['target_id'])->toBe('7600');
});

it('accepts several pages at once', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedPageScope($workspace);

    $rule = makePageScopedRule($workspace, 'ad_set', [$ctx['alpha']->id, $ctx['bravo']->id]);
    $proposals = (new OptimizationRuleEvaluator)->plan($rule, $ctx['account']);

    expect($proposals)->toHaveCount(2);
});

it('stores the selected pages when a rule is saved', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $ctx = seedPageScope($workspace);

    $payload = [
        'name' => 'Page scoped',
        'meta_ads_account_ids' => ['720'],
        'meta_ads_page_ids' => [(string) $ctx['alpha']->id, (string) $ctx['bravo']->id],
        'target_type' => 'ad_set',
        'condition_operator' => 'and',
        'action' => 'pause',
        'is_active' => true,
        'priority' => 0,
        'frequency' => 'hourly',
        'execution_mode' => 'approval',
        'conditions' => [['metric' => 'roas', 'operator' => '<', 'value' => 3, 'time_window' => 'today']],
    ];

    $this->post(route('workspaces.metaads.optimization-rules.store', ['workspace' => $workspace]), $payload)
        ->assertSessionHasNoErrors();

    expect(OptimizationRule::latest('id')->first()->pages->pluck('id')->sort()->values()->all())
        ->toBe([$ctx['alpha']->id, $ctx['bravo']->id]);
});

it('rejects a page from another workspace', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedPageScope($workspace);

    $this->post(route('workspaces.metaads.optimization-rules.store', ['workspace' => $workspace]), [
        'name' => 'Foreign page',
        'meta_ads_account_ids' => ['720'],
        'meta_ads_page_ids' => ['999000111'],
        'target_type' => 'ad_set',
        'condition_operator' => 'and',
        'action' => 'pause',
        'is_active' => true,
        'priority' => 0,
        'frequency' => 'hourly',
        'execution_mode' => 'approval',
        'conditions' => [['metric' => 'roas', 'operator' => '<', 'value' => 3, 'time_window' => 'today']],
    ])->assertSessionHasErrors('meta_ads_page_ids.0');
});
