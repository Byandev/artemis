<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->owner->id,
        'discord_notifications_module_enabled' => true,
        'erp_integration_module_enabled' => true,
    ]);
});

it('serves ERP credential settings while the module is on', function () {
    $this->actingAs($this->owner)
        ->get(route('erp-credentials.edit', ['workspace' => $this->workspace->slug]))
        ->assertOk();
});

it('404s every ERP credential route when the module is switched off', function () {
    $this->workspace->update(['erp_integration_module_enabled' => false]);

    $slug = $this->workspace->slug;

    $this->actingAs($this->owner)->get(route('erp-credentials.edit', ['workspace' => $slug]))->assertNotFound();
    $this->actingAs($this->owner)->put(route('erp-credentials.update', ['workspace' => $slug]), [
        'erp_username' => 'someone',
    ])->assertNotFound();
    $this->actingAs($this->owner)->delete(route('erp-credentials.destroy', ['workspace' => $slug]))->assertNotFound();
});

it('serves Discord notification settings while the module is on', function () {
    $this->actingAs($this->owner)
        ->get(route('notifications.edit', ['workspace' => $this->workspace->slug]))
        ->assertOk();
});

it('404s every Discord notification route when the module is switched off', function () {
    $this->workspace->update(['discord_notifications_module_enabled' => false]);

    $slug = $this->workspace->slug;

    $this->actingAs($this->owner)->get(route('notifications.edit', ['workspace' => $slug]))->assertNotFound();
    $this->actingAs($this->owner)->put(route('notifications.update', ['workspace' => $slug]), [
        'deliveries_webhook_url' => null,
        'awaiting_webhook_url' => null,
        'deliveries_enabled' => false,
        'deliveries_send_at' => '09:00',
        'awaiting_enabled' => false,
        'awaiting_send_at' => '09:00',
    ])->assertNotFound();
});

it('hides the Discord permission from the role editor when the module is off', function () {
    $this->workspace->update(['discord_notifications_module_enabled' => false]);

    expect($this->workspace->fresh()->hiddenPermissionNames())
        ->toContain(PermissionEnum::ManageDiscordNotifications->value);
});

it('keeps the Discord permission available when the module is on', function () {
    expect($this->workspace->fresh()->hiddenPermissionNames())
        ->not->toContain(PermissionEnum::ManageDiscordNotifications->value);
});
