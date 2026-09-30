<?php

use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * Toggling a CSR between ACTIVE and INACTIVE from the roster's edit dialog.
 *
 * The column arrived with a lowercase `active` default and a later migration
 * changed the default without rewriting the rows behind it, so the dialog could
 * be handed a value the endpoint then refused — a CSR whose status this app had
 * written was unsaveable. Case is folded on the way in; anything that isn't one
 * of the two statuses is still rejected.
 */
beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
    $this->shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
});

/** A CSR on this workspace's shops. The pivot carries a uuid of its own. */
function statusCsr(Workspace $workspace, int $shopId, string $status = 'ACTIVE'): PancakeUser
{
    $csr = PancakeUser::create(['name' => 'Charis Luna', 'status' => $status]);

    DB::table('pancake_shop_users')->insert([
        'id' => (string) Str::uuid(),
        'shop_id' => $shopId,
        'user_id' => $csr->id,
    ]);

    return $csr;
}

it('deactivates a CSR', function () {
    $csr = statusCsr($this->workspace, $this->shop->id);

    $this->actingAs($this->owner)
        ->put("/workspaces/{$this->workspace->slug}/employees/{$csr->id}", [
            'status' => 'INACTIVE',
            'user_id' => '',
        ])
        ->assertRedirect();

    expect($csr->fresh()->status)->toBe('INACTIVE');
});

it('saves a CSR still holding the old lowercase status', function () {
    $csr = statusCsr($this->workspace, $this->shop->id, 'active');

    // What the dialog posts back when it was seeded from that row.
    $this->actingAs($this->owner)
        ->put("/workspaces/{$this->workspace->slug}/employees/{$csr->id}", [
            'status' => 'inactive',
            'user_id' => '',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($csr->fresh()->status)->toBe('INACTIVE');
});

it('rejects a status that is neither ACTIVE nor INACTIVE', function () {
    $csr = statusCsr($this->workspace, $this->shop->id);

    $this->actingAs($this->owner)
        ->put("/workspaces/{$this->workspace->slug}/employees/{$csr->id}", [
            'status' => 'RETIRED',
            'user_id' => '',
        ])
        ->assertSessionHasErrors('status');

    expect($csr->fresh()->status)->toBe('ACTIVE');
});

it('maps a CSR to a system user and back to unassigned', function () {
    $csr = statusCsr($this->workspace, $this->shop->id);
    $url = "/workspaces/{$this->workspace->slug}/employees/{$csr->id}";

    $this->actingAs($this->owner)
        ->put($url, ['status' => 'ACTIVE', 'user_id' => $this->owner->id])
        ->assertRedirect();

    expect($csr->fresh()->user_id)->toBe($this->owner->id);

    $this->actingAs($this->owner)
        ->put($url, ['status' => 'ACTIVE', 'user_id' => ''])
        ->assertRedirect();

    expect($csr->fresh()->user_id)->toBeNull();
});

it('refuses a CSR that is not on this workspace', function () {
    $other = Shop::factory()->create();
    $csr = statusCsr($this->workspace, $other->id);

    $this->actingAs($this->owner)
        ->put("/workspaces/{$this->workspace->slug}/employees/{$csr->id}", [
            'status' => 'INACTIVE',
            'user_id' => '',
        ])
        ->assertNotFound();

    expect($csr->fresh()->status)->toBe('ACTIVE');
});
