<?php

use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('member can submit a support ticket', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    $payload = [
        'category' => 'bug',
        'subject' => 'Something broke',
        'description' => 'Steps to reproduce the issue.',
        'current_url' => 'https://app.local/workspaces/acme/dashboard',
        'user_agent' => 'Pest/SupportTicket',
    ];

    $response = $this->actingAs($user)->post(
        "/workspaces/{$workspace->slug}/support",
        $payload
    );

    $response->assertRedirect(route('support.index', $workspace));

    $ticket = SupportTicket::query()->first();

    expect($ticket->reference)->toStartWith('SUP-');

    $this->assertDatabaseHas('support_tickets', [
        'id' => $ticket->id,
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'category' => 'bug',
        'subject' => 'Something broke',
        'status' => 'open',
    ]);
});

test('member only sees their own support tickets', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    $workspace->users()->attach($otherUser->id, ['role' => 'member']);

    SupportTicket::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    SupportTicket::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $otherUser->id,
    ]);

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/support")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/support/index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.user_id', $user->id)
        );
});

test('non-member and guest cannot access support pages', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/support")
        ->assertForbidden();

    $this->get("/workspaces/{$workspace->slug}/support")
        ->assertRedirect(route('login'));
});

test('admin can view all support tickets', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($owner)->create();

    $workspace->users()->attach($member->id, ['role' => 'member']);

    SupportTicket::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);

    SupportTicket::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/admin/support-tickets")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/admin/support-tickets/index')
            ->has('tickets.data', 2)
        );
});

test('non-admin cannot view admin support tickets', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($owner)->create();

    $workspace->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member)
        ->get("/workspaces/{$workspace->slug}/admin/support-tickets")
        ->assertForbidden();
});

test('admin can update ticket status and invalid transitions fail', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($owner)->create();

    $ticket = SupportTicket::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'status' => 'open',
    ]);

    $this->actingAs($owner)
        ->patch("/workspaces/{$workspace->slug}/admin/support-tickets/{$ticket->id}", [
            'status' => 'in_progress',
        ])
        ->assertRedirect(route('admin.support-tickets.index', $workspace));

    $this->assertDatabaseHas('support_tickets', [
        'id' => $ticket->id,
        'status' => 'in_progress',
    ]);

    $ticket->refresh();
    $ticket->update(['status' => 'closed']);

    $this->actingAs($owner)
        ->patchJson("/workspaces/{$workspace->slug}/admin/support-tickets/{$ticket->id}", [
            'status' => 'open',
        ])
        ->assertUnprocessable();
});

test('support ticket validation fails with missing fields', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    $this->actingAs($user)
        ->postJson("/workspaces/{$workspace->slug}/support", [])
        ->assertUnprocessable();
});
