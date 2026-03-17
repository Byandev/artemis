<?php

use App\Models\CustomerServiceRepresentative;
use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use App\Models\Workspace;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('workspace members can fetch csr performance ranked by sales', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $page = Page::factory()->forWorkspace($workspace)->forOwner($user)->create();

    $alpha = CustomerServiceRepresentative::create([
        'id' => 101,
        'uuid' => 'csr-alpha',
        'name' => 'Alpha CSR',
        'email' => 'alpha@example.com',
    ]);

    $beta = CustomerServiceRepresentative::create([
        'id' => 202,
        'uuid' => 'csr-beta',
        'name' => 'Beta CSR',
        'email' => 'beta@example.com',
    ]);

    Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'assignee_id' => $alpha->id,
        'final_amount' => 150,
        'confirmed_at' => now()->startOfMonth()->addDay(),
    ]);

    Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'assignee_id' => $alpha->id,
        'final_amount' => 50,
        'confirmed_at' => now()->startOfMonth()->addDays(2),
    ]);

    Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'assignee_id' => $beta->id,
        'final_amount' => 120,
        'confirmed_at' => now()->startOfMonth()->addDays(3),
    ]);

    $response = $this->actingAs($user)->getJson(route('api.workspaces.csrs.performance.index', [
        'workspace' => $workspace,
        'period' => 'monthly',
        'sort_by' => 'sales',
        'sort_dir' => 'desc',
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Alpha CSR')
        ->assertJsonPath('data.0.rank', 1)
        ->assertJsonPath('data.0.total_orders', 2)
        ->assertJsonPath('data.0.total_sales', 200)
        ->assertJsonPath('data.1.name', 'Beta CSR')
        ->assertJsonPath('data.1.rank', 2)
        ->assertJsonPath('data.1.total_orders', 1)
        ->assertJsonPath('data.1.total_sales', 120);
});

test('guests can fetch public csr performance', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $page = Page::factory()->forWorkspace($workspace)->forOwner($user)->create();

    $csr = CustomerServiceRepresentative::create([
        'id' => 303,
        'uuid' => 'csr-public',
        'name' => 'Public CSR',
        'email' => 'public@example.com',
    ]);

    Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'assignee_id' => $csr->id,
        'final_amount' => 300,
        'confirmed_at' => now()->startOfMonth()->addDay(),
    ]);

    $response = $this->getJson(route('api.public.workspaces.csrs.performance.index', [
        'workspace' => $workspace,
        'period' => 'monthly',
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Public CSR')
        ->assertJsonPath('data.0.total_orders', 1)
        ->assertJsonPath('data.0.total_sales', 300);
});