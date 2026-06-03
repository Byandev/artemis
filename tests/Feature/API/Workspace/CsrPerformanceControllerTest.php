<?php

use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function seedCsr(string $name, string $fbId): array
{
    $id = (string) Str::uuid();
    DB::table('pancake_users')->insert([
        'id' => $id,
        'name' => $name,
        'fb_id' => $fbId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['id' => $id, 'fb_id' => $fbId];
}

test('workspace members can fetch csr performance ranked by sales', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $page = Page::factory()->forWorkspace($workspace)->forOwner($user)->create();

    $alpha = seedCsr('Alpha CSR', '111000');
    $beta = seedCsr('Beta CSR', '222000');

    Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'assignee_id' => $alpha['fb_id'],
        'final_amount' => 150,
        'confirmed_at' => now()->startOfMonth()->addDay(),
    ]);

    Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'assignee_id' => $alpha['fb_id'],
        'final_amount' => 50,
        'confirmed_at' => now()->startOfMonth()->addDays(2),
    ]);

    Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'assignee_id' => $beta['fb_id'],
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

    $csr = seedCsr('Public CSR', '333000');

    Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'assignee_id' => $csr['fb_id'],
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
