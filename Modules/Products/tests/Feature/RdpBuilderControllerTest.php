<?php

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Products\Models\ProductForm;
use Modules\Products\Models\Rdp;
use Modules\Products\Models\TargetMarket;
use Tests\TestCase;

// Module test dirs aren't bound by the root tests/Pest.php (->in('Feature') only
// covers tests/Feature), so extend the app TestCase explicitly to boot the app.
uses(TestCase::class, RefreshDatabase::class);

/**
 * A workspace with one form and one Musculoskeletal > Back Pain market, which
 * is the pairing the builder's header chip is drawn from.
 *
 * @return array{user: User, workspace: Workspace, form: ProductForm, category: TargetMarket, sub: TargetMarket}
 */
function rdpFixtures(): array
{
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $form = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Balm']);
    $category = TargetMarket::create(['workspace_id' => $workspace->id, 'name' => 'Musculoskeletal']);
    $sub = TargetMarket::create([
        'workspace_id' => $workspace->id,
        'parent_id' => $category->id,
        'name' => 'Back Pain',
    ]);

    return compact('owner', 'workspace', 'form', 'category', 'sub') + ['user' => $owner];
}

test('owner can view the RDPs list and open the builder', function () {
    ['user' => $owner, 'workspace' => $workspace] = rdpFixtures();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('workspaces/products/rdp-builder/index'));

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder/create")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/products/rdp-builder/builder')
            ->where('rdp', null)
            ->count('forms', 1)
            // The markets carry their sub categories so the third select can
            // narrow without another round trip.
            ->count('targetMarkets', 1)
            ->count('targetMarkets.0.children', 1)
        );
});

test('the pages 404 for a workspace without the products module', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // Owners hold '*', so the permission checks alone would wave them through.
    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder")
        ->assertNotFound();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder/create")
        ->assertNotFound();
});

test('non-member cannot reach the RDPs list', function () {
    ['workspace' => $workspace] = rdpFixtures();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder")
        ->assertForbidden();
});

test('reading and building are separate permissions', function () {
    ['workspace' => $workspace, 'form' => $form, 'category' => $category] = rdpFixtures();

    $reader = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewRdpBuilder->value],
        'Products',
    );

    $this->actingAs($reader)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder")
        ->assertOk();

    // The builder itself writes, so reading the list is not enough to open it.
    $this->actingAs($reader)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder/create")
        ->assertForbidden();

    $this->actingAs($reader)
        ->post("/workspaces/{$workspace->slug}/products/rdp-builder", [
            'name' => 'Back Ease Balm',
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertForbidden();
});

test('a brief is saved with its brief, name and lab notes', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category, 'sub' => $sub] = rdpFixtures();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/rdp-builder", [
            'name' => 'Back Ease Balm',
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'target_market_sub_id' => $sub->id,
            'claims' => "Fast relief\nNon-greasy",
            'active_ingredients' => "Menthol\nCamphor",
            'additional_instruction' => 'Keep the scent light.',
        ])
        ->assertRedirect("/workspaces/{$workspace->slug}/products/rdp-builder");

    $rdp = Rdp::ofWorkspace($workspace)->firstOrFail();

    expect($rdp->name)->toBe('Back Ease Balm')
        ->and($rdp->product_form_id)->toBe($form->id)
        ->and($rdp->target_market_sub_id)->toBe($sub->id)
        // The multi-line fields are stored as typed, one item per line.
        ->and($rdp->claims)->toBe("Fast relief\nNon-greasy")
        ->and($rdp->additional_instruction)->toBe('Keep the scent light.')
        // Stamped with whoever built it — the list has a column for it.
        ->and($rdp->created_by)->toBe($owner->id);
});

test('store requires a name, a form and a market', function () {
    ['user' => $owner, 'workspace' => $workspace] = rdpFixtures();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/rdp-builder", [])
        ->assertSessionHasErrors(['name', 'product_form_id', 'target_market_id']);
});

test('the sub category has to belong to the market picked beside it', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = rdpFixtures();

    $otherCategory = TargetMarket::create(['workspace_id' => $workspace->id, 'name' => 'Respiratory']);
    $otherSub = TargetMarket::create([
        'workspace_id' => $workspace->id,
        'parent_id' => $otherCategory->id,
        'name' => 'Asthma',
    ]);

    // Asthma under Musculoskeletal would render a chip that pairs two
    // unrelated names.
    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/rdp-builder", [
            'name' => 'Back Ease Balm',
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'target_market_sub_id' => $otherSub->id,
        ])
        ->assertSessionHasErrors('target_market_sub_id');

    // A category with nothing under it saves without one.
    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/rdp-builder", [
            'name' => 'Clear Air Drops',
            'product_form_id' => $form->id,
            'target_market_id' => $otherCategory->id,
        ])
        ->assertSessionHasNoErrors();
});

test('a market must be a top-level one, and from this workspace', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'sub' => $sub] = rdpFixtures();
    ['workspace' => $other] = makeProductsWorkspace();

    $foreign = TargetMarket::create(['workspace_id' => $other->id, 'name' => 'Cardiovascular']);
    $foreignForm = ProductForm::create(['workspace_id' => $other->id, 'name' => 'Spray']);

    // A sub category in the "Target market" slot would make the header chip
    // nonsense and orphan the third select.
    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/rdp-builder", [
            'name' => 'Back Ease Balm',
            'product_form_id' => $form->id,
            'target_market_id' => $sub->id,
        ])
        ->assertSessionHasErrors('target_market_id');

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/rdp-builder", [
            'name' => 'Back Ease Balm',
            'product_form_id' => $foreignForm->id,
            'target_market_id' => $foreign->id,
        ])
        ->assertSessionHasErrors(['product_form_id', 'target_market_id']);
});

test('opening a saved brief loads it back into the builder', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category, 'sub' => $sub] = rdpFixtures();

    $rdp = Rdp::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'product_form_id' => $form->id,
        'target_market_id' => $category->id,
        'target_market_sub_id' => $sub->id,
        'name' => 'Back Ease Balm',
        'claims' => 'Fast relief',
    ]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder/{$rdp->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/products/rdp-builder/builder')
            ->where('rdp.name', 'Back Ease Balm')
            ->where('rdp.claims', 'Fast relief')
            ->where('rdp.target_market_sub_id', $sub->id)
        );
});

test('update saves over the brief without filing a second one', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = rdpFixtures();

    $rdp = Rdp::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'product_form_id' => $form->id,
        'target_market_id' => $category->id,
        'name' => 'Back Ease Balm',
    ]);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/rdp-builder/{$rdp->id}", [
            'name' => 'Back Ease Balm Plus',
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'additional_instruction' => 'Bigger tub.',
        ])
        ->assertRedirect("/workspaces/{$workspace->slug}/products/rdp-builder");

    expect(Rdp::ofWorkspace($workspace)->count())->toBe(1)
        ->and($rdp->refresh()->name)->toBe('Back Ease Balm Plus')
        ->and($rdp->additional_instruction)->toBe('Bigger tub.');
});

test('a brief from another workspace is not reachable', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = rdpFixtures();
    ['user' => $stranger, 'workspace' => $other, 'form' => $otherForm, 'category' => $otherCategory] = rdpFixtures();

    $foreign = Rdp::create([
        'workspace_id' => $other->id,
        'created_by' => $stranger->id,
        'product_form_id' => $otherForm->id,
        'target_market_id' => $otherCategory->id,
        'name' => 'Theirs',
    ]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder/{$foreign->id}/edit")
        ->assertNotFound();

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/rdp-builder/{$foreign->id}", [
            'name' => 'Mine now',
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertNotFound();
});

test('the list shows the sub category, the date and who built it', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category, 'sub' => $sub] = rdpFixtures();

    $rdp = Rdp::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'product_form_id' => $form->id,
        'target_market_id' => $category->id,
        'target_market_sub_id' => $sub->id,
        'name' => 'Back Ease Balm',
    ]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder")
        ->assertInertia(fn ($page) => $page
            ->count('rdps', 1)
            ->where('rdps.0.name', 'Back Ease Balm')
            ->where('rdps.0.form', 'Balm')
            // Back Pain, not Musculoskeletal.
            ->where('rdps.0.target_market', 'Back Pain')
            ->where('rdps.0.date', $rdp->created_at->toDateString())
            ->where('rdps.0.created_by', $owner->name)
        );
});

test('a brief outlives the taxonomy it was filed under', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category, 'sub' => $sub] = rdpFixtures();

    $rdp = Rdp::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'product_form_id' => $form->id,
        'target_market_id' => $category->id,
        'target_market_sub_id' => $sub->id,
        'name' => 'Back Ease Balm',
    ]);

    // Deleting the category cascades to its sub category; both are nulled on
    // the brief rather than taking it with them.
    $category->delete();
    $form->delete();

    expect($rdp->refresh()->exists)->toBeTrue()
        ->and($rdp->target_market_id)->toBeNull()
        ->and($rdp->target_market_sub_id)->toBeNull()
        ->and($rdp->product_form_id)->toBeNull();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rdps.0.name', 'Back Ease Balm')
            ->where('rdps.0.form', null)
            ->where('rdps.0.target_market', null)
        );
});
