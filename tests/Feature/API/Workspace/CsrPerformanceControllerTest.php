<?php

use App\Models\CustomerServiceRepresentative;
use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use App\Models\Workspace;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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
