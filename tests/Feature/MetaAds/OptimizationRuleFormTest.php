<?php

use Inertia\Testing\AssertableInertia as Assert;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\User as MetaUser;

it('still shows both execution modes on the form (automatic is shown but disabled)', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->get(route('workspaces.metaads.optimization-rules.create', ['workspace' => $workspace]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('options.executionModes', ['approval', 'automatic'])
        );
});

it('rejects the automatic execution mode on save while it is disabled', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $metaUser = MetaUser::create(['id' => 9100, 'name' => 'U', 'access_token' => 't']);
    $metaUser->workspaces()->attach($workspace->id);
    $account = AdAccount::create(['id' => 360, 'name' => 'A']);
    $metaUser->adAccounts()->attach($account->id);

    $payload = [
        'name' => 'R',
        'meta_ads_account_ids' => ['360'],
        'target_type' => 'ad_set',
        'condition_operator' => 'and',
        'action' => 'pause',
        'is_active' => true,
        'priority' => 0,
        // Required since rules became scheduled.
        'frequency' => 'hourly',
        'conditions' => [['metric' => 'roas', 'operator' => '<', 'value' => 3, 'time_window' => 'today']],
    ];

    $this->post(route('workspaces.metaads.optimization-rules.store', ['workspace' => $workspace]), [
        ...$payload, 'execution_mode' => 'automatic',
    ])->assertSessionHasErrors('execution_mode');

    // approval still works.
    $this->post(route('workspaces.metaads.optimization-rules.store', ['workspace' => $workspace]), [
        ...$payload, 'execution_mode' => 'approval',
    ])->assertSessionHasNoErrors();
});
