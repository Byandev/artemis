<?php

use App\Models\User;
use Modules\SimGateway\Models\Sim;

it('lets a workspace member view their SIMs', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    Sim::factory()->create(['workspace_id' => $workspace->id, 'status' => 'active']);

    $this->get(route('workspaces.sms.sims', $workspace->slug))
        ->assertOk();
});

it('forbids a non-member from viewing SIMs', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs(User::factory()->create())
        ->getJson(route('workspaces.sms.sims', $workspace->slug))
        ->assertForbidden();
});
