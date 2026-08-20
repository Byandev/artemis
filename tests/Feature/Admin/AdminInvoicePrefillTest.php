<?php

use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Billing\Models\WorkspaceBillingDetail;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
});

it('exposes null billing details for a workspace that has never saved any', function () {
    Workspace::factory()->create(['billing_module_enabled' => true]);

    $this->actingAs($this->admin)
        ->get(route('admin.invoices.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/invoices/create')
            ->where('workspaces.0.billing_name', null)
            ->where('workspaces.0.billing_email', null)
            ->where('workspaces.0.billing_address', null)
        );
});

it('exposes the workspace saved billing details to the invoice form', function () {
    $workspace = Workspace::factory()->create(['billing_module_enabled' => true]);

    WorkspaceBillingDetail::create([
        'workspace_id' => $workspace->id,
        'billing_name' => 'Meta Digitrading Enterprise Co',
        'billing_email' => 'accounts@example.com',
        'billing_address' => "123 Ayala Ave\nMakati City",
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.invoices.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/invoices/create')
            ->where('workspaces.0.billing_name', 'Meta Digitrading Enterprise Co')
            ->where('workspaces.0.billing_email', 'accounts@example.com')
            ->where('workspaces.0.billing_address', "123 Ayala Ave\nMakati City")
        );
});

it('keeps each workspace billing details separate', function () {
    $alpha = Workspace::factory()->create(['name' => 'Alpha Workspace']);
    $beta = Workspace::factory()->create(['name' => 'Beta Workspace']);

    WorkspaceBillingDetail::create([
        'workspace_id' => $alpha->id,
        'billing_name' => 'Alpha Billing',
    ]);
    WorkspaceBillingDetail::create([
        'workspace_id' => $beta->id,
        'billing_name' => 'Beta Billing',
    ]);

    // The controller orders workspaces by name, so Alpha is index 0.
    $this->actingAs($this->admin)
        ->get(route('admin.invoices.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspaces.0.name', 'Alpha Workspace')
            ->where('workspaces.0.billing_name', 'Alpha Billing')
            ->where('workspaces.1.name', 'Beta Workspace')
            ->where('workspaces.1.billing_name', 'Beta Billing')
        );
});

it('still exposes the owner details as the fallback when billing details are blank', function () {
    $owner = User::factory()->create(['name' => 'Neil Mulingbayan', 'email' => 'owner@example.com']);
    Workspace::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.invoices.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspaces.0.owner_name', 'Neil Mulingbayan')
            ->where('workspaces.0.owner_email', 'owner@example.com')
            ->where('workspaces.0.billing_name', null)
        );
});

it('surfaces billing details saved through the workspace settings page', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'billing_module_enabled' => true,
    ]);

    // Save via the real settings endpoint, then read it back on the admin form.
    $this->actingAs($owner)
        ->put(route('billing-settings.update', ['workspace' => $workspace->slug]), [
            'billing_name' => 'Saved Through Settings',
            'billing_email' => 'settings@example.com',
            'billing_address' => 'Magahis, Tuy Batangas',
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->get(route('admin.invoices.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspaces.0.billing_name', 'Saved Through Settings')
            ->where('workspaces.0.billing_email', 'settings@example.com')
            ->where('workspaces.0.billing_address', 'Magahis, Tuy Batangas')
        );
});
