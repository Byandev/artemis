<?php

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Products\Database\Seeders\TargetMarketSeeder;
use Modules\Products\Models\TargetMarket;
use Tests\TestCase;

// Module test dirs aren't bound by the root tests/Pest.php (->in('Feature') only
// covers tests/Feature), so extend the app TestCase explicitly to boot the app.
uses(TestCase::class, RefreshDatabase::class);

/**
 * A category with the given sub categories under it.
 */
function targetMarketTree(int $workspaceId, string $name, array $children = []): TargetMarket
{
    $category = TargetMarket::create(['workspace_id' => $workspaceId, 'name' => $name]);

    foreach ($children as $child) {
        TargetMarket::create([
            'workspace_id' => $workspaceId,
            'parent_id' => $category->id,
            'name' => $child,
        ]);
    }

    return $category;
}

test('owner can view the target market page', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/target-markets")
        ->assertOk();
});

test('the page 404s for a workspace without the products module', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // Owners hold '*', so the permission check alone would wave them through.
    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/target-markets")
        ->assertNotFound();
});

test('non-member cannot view the target market page', function () {
    ['workspace' => $workspace] = makeProductsWorkspace();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/workspaces/{$workspace->slug}/products/target-markets")
        ->assertForbidden();
});

test('viewing and managing are separate permissions', function () {
    ['workspace' => $workspace] = makeProductsWorkspace();

    $viewer = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewTargetMarkets->value],
        'Products',
    );

    $this->actingAs($viewer)
        ->get("/workspaces/{$workspace->slug}/products/target-markets")
        ->assertOk();

    $this->actingAs($viewer)
        ->post("/workspaces/{$workspace->slug}/products/target-markets", ['name' => 'Cardiovascular'])
        ->assertForbidden();

    $manager = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewTargetMarkets->value, Permission::ManageTargetMarkets->value],
        'Products',
    );

    $this->actingAs($manager)
        ->post("/workspaces/{$workspace->slug}/products/target-markets", ['name' => 'Cardiovascular'])
        ->assertRedirect("/workspaces/{$workspace->slug}/products/target-markets");
});

test('store adds a category at the top level and a sub category under one', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/target-markets", [
            'name' => 'Cardiovascular',
        ])
        ->assertRedirect("/workspaces/{$workspace->slug}/products/target-markets");

    $category = TargetMarket::where('name', 'Cardiovascular')->firstOrFail();
    expect($category->parent_id)->toBeNull();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/target-markets", [
            'parent_id' => $category->id,
            'name' => 'Hypertension',
        ])
        ->assertSessionHasNoErrors();

    expect($category->children()->pluck('name')->all())->toBe(['Hypertension']);
});

test('the tree is only two levels deep', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $category = targetMarketTree($workspace->id, 'Cardiovascular', ['Hypertension']);
    $sub = $category->children()->firstOrFail();

    // Filing under a sub category would make a third level the page can't draw.
    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/target-markets", [
            'parent_id' => $sub->id,
            'name' => 'Stage 2',
        ])
        ->assertSessionHasErrors('parent_id');
});

test('a parent from another workspace is refused', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();
    ['workspace' => $other] = makeProductsWorkspace();

    $foreign = targetMarketTree($other->id, 'Cardiovascular');

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/target-markets", [
            'parent_id' => $foreign->id,
            'name' => 'Hypertension',
        ])
        ->assertSessionHasErrors('parent_id');
});

test('a name must be unique among its siblings, but may repeat elsewhere', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $cardio = targetMarketTree($workspace->id, 'Cardiovascular', ['Hypertension']);
    $respiratory = targetMarketTree($workspace->id, 'Respiratory');

    // Same name, same parent -> refused.
    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/target-markets", [
            'parent_id' => $cardio->id,
            'name' => 'Hypertension',
        ])
        ->assertSessionHasErrors('name');

    // Same name under a different category -> fine.
    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/target-markets", [
            'parent_id' => $respiratory->id,
            'name' => 'Hypertension',
        ])
        ->assertSessionHasNoErrors();

    // Two top-level rows with one name -> refused, which the database's own
    // unique index would not have caught (NULL parents compare as distinct).
    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/target-markets", [
            'name' => 'Cardiovascular',
        ])
        ->assertSessionHasErrors('name');
});

test('update renames a row and leaves where it sits alone', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $category = targetMarketTree($workspace->id, 'Cardiovascular', ['Hypertension']);
    $sub = $category->children()->firstOrFail();

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/target-markets/{$sub->id}", [
            'name' => 'High Blood Pressure',
            // Ignored: the rename endpoint does not move rows.
            'parent_id' => null,
        ])
        ->assertRedirect("/workspaces/{$workspace->slug}/products/target-markets");

    $sub->refresh();

    expect($sub->name)->toBe('High Blood Pressure')
        ->and($sub->parent_id)->toBe($category->id);
});

test('renaming a row onto a sibling name is refused, but keeping its own is fine', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $category = targetMarketTree($workspace->id, 'Cardiovascular', ['Hypertension', 'Arrhythmia']);
    $sub = $category->children()->where('name', 'Arrhythmia')->firstOrFail();

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/target-markets/{$sub->id}", [
            'name' => 'Hypertension',
        ])
        ->assertSessionHasErrors('name');

    // Saving a row under its own unchanged name must not trip the uniqueness
    // rule against itself.
    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/target-markets/{$sub->id}", [
            'name' => 'Arrhythmia',
        ])
        ->assertSessionHasNoErrors();
});

test('deleting a category takes its sub categories with it', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $category = targetMarketTree($workspace->id, 'Cardiovascular', ['Hypertension', 'Arrhythmia']);
    $childIds = $category->children()->pluck('id')->all();

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}/products/target-markets/{$category->id}")
        ->assertRedirect("/workspaces/{$workspace->slug}/products/target-markets");

    expect(TargetMarket::find($category->id))->toBeNull()
        ->and(TargetMarket::whereIn('id', $childIds)->count())->toBe(0);
});

test('deleting a sub category leaves its category standing', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $category = targetMarketTree($workspace->id, 'Cardiovascular', ['Hypertension']);
    $sub = $category->children()->firstOrFail();

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}/products/target-markets/{$sub->id}")
        ->assertSessionHasNoErrors();

    expect(TargetMarket::find($category->id))->not->toBeNull()
        ->and($category->children()->count())->toBe(0);
});

test('a row from another workspace is not reachable', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();
    ['workspace' => $other] = makeProductsWorkspace();

    $foreign = targetMarketTree($other->id, 'Cardiovascular');

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/target-markets/{$foreign->id}", ['name' => 'Mine'])
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}/products/target-markets/{$foreign->id}")
        ->assertNotFound();
});

test('index paginates categories and counts the whole tree', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    // 12 categories, two sub categories each -> two pages of 10.
    foreach (range(1, 12) as $n) {
        targetMarketTree($workspace->id, sprintf('Category %02d', $n), ['A', 'B']);
    }

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/target-markets")
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/products/target-markets/index')
            ->where('categories.total', 12)
            ->where('categories.per_page', 10)
            ->count('categories.data', 10)
            // The header totals describe the tree, not the page.
            ->where('summary.categories', 12)
            ->where('summary.sub_categories', 24)
            // Every category is offered by the "Add under" picker, including
            // the ones on page two.
            ->count('parents', 12)
        );

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/target-markets?per_page=25")
        ->assertInertia(fn ($page) => $page->count('categories.data', 12));
});

test('search matches a category, and surfaces the category of a matching sub category', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    targetMarketTree($workspace->id, 'Cardiovascular', ['Hypertension']);
    targetMarketTree($workspace->id, 'Respiratory', ['Asthma']);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/target-markets?search=cardio")
        ->assertInertia(fn ($page) => $page
            ->count('categories.data', 1)
            ->where('categories.data.0.name', 'Cardiovascular')
        );

    // A sub category's name pulls its category into the results — otherwise
    // the row would be invisible to the search box above it.
    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/target-markets?search=asthma")
        ->assertInertia(fn ($page) => $page
            ->count('categories.data', 1)
            ->where('categories.data.0.name', 'Respiratory')
        );

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/target-markets?search=nothingmatches")
        ->assertInertia(fn ($page) => $page->count('categories.data', 0));
});

test('another workspace tree never leaks in', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();
    ['workspace' => $other] = makeProductsWorkspace();

    targetMarketTree($workspace->id, 'Mine', ['One']);
    targetMarketTree($other->id, 'Theirs', ['Two']);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/target-markets")
        ->assertInertia(fn ($page) => $page
            ->count('categories.data', 1)
            ->where('categories.data.0.name', 'Mine')
            ->where('summary.categories', 1)
            ->where('summary.sub_categories', 1)
        );
});

test('a new entry lands at the end of its level, not in alphabetical order', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    // Seeded order is curated, so "Aardvark" must not jump to the front.
    TargetMarket::create(['workspace_id' => $workspace->id, 'name' => 'Cardiovascular', 'position' => 0]);
    TargetMarket::create(['workspace_id' => $workspace->id, 'name' => 'Respiratory', 'position' => 1]);

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/target-markets", ['name' => 'Aardvark'])
        ->assertSessionHasNoErrors();

    expect(
        TargetMarket::ofWorkspace($workspace)->categories()->ordered()->pluck('name')->all()
    )->toBe(['Cardiovascular', 'Respiratory', 'Aardvark']);

    // And the same for a sub category under its own parent.
    $cardio = TargetMarket::where('name', 'Cardiovascular')->firstOrFail();

    foreach (['Hypertension', 'Arrhythmia'] as $name) {
        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->slug}/products/target-markets", [
                'parent_id' => $cardio->id,
                'name' => $name,
            ])
            ->assertSessionHasNoErrors();
    }

    expect($cardio->children()->pluck('name')->all())->toBe(['Hypertension', 'Arrhythmia']);
});

test('the seeder fills a products workspace with the standard categories, in order', function () {
    ['workspace' => $workspace] = makeProductsWorkspace();
    ['workspace' => $without] = makeWorkspaceWithOwner();

    (new TargetMarketSeeder)->run();

    expect(
        TargetMarket::ofWorkspace($workspace)->categories()->ordered()->pluck('name')->all()
    )->toBe(TargetMarketSeeder::CATEGORIES)
        // A workspace without the module is left alone.
        ->and(TargetMarket::where('workspace_id', $without->id)->count())->toBe(0);
});

test('the seeder files the known sub categories under their category', function () {
    ['workspace' => $workspace] = makeProductsWorkspace();

    (new TargetMarketSeeder)->run();

    $musculoskeletal = TargetMarket::ofWorkspace($workspace)
        ->where('name', 'Musculoskeletal')
        ->firstOrFail();

    $cardiovascular = TargetMarket::ofWorkspace($workspace)
        ->where('name', 'Cardiovascular')
        ->firstOrFail();

    expect($musculoskeletal->children()->pluck('name')->all())
        ->toBe(TargetMarketSeeder::SUB_CATEGORIES['Musculoskeletal'])
        ->and($cardiovascular->children()->pluck('name')->all())
        ->toBe(TargetMarketSeeder::SUB_CATEGORIES['Cardiovascular'])
        // A category we have no list for is seeded bare rather than guessed at.
        ->and(
            TargetMarket::ofWorkspace($workspace)->where('name', 'Oncology')->firstOrFail()->children()->count()
        )->toBe(0);
});

test('re-running the seeder disturbs nothing that has been edited since', function () {
    ['workspace' => $workspace] = makeProductsWorkspace();

    (new TargetMarketSeeder)->run();

    $cardio = TargetMarket::ofWorkspace($workspace)->where('name', 'Cardiovascular')->firstOrFail();
    $seeded = $cardio->children()->count();

    // Deliberately a name the seeder does not own: the point is that a row
    // somebody typed survives a re-run, and reusing a seeded name would only
    // prove firstOrCreate matched it.
    $cardio->children()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Custom Condition',
        'position' => 99,
    ]);
    $cardio->update(['position' => 99]);

    (new TargetMarketSeeder)->run();

    expect(TargetMarket::ofWorkspace($workspace)->categories()->count())
        ->toBe(count(TargetMarketSeeder::CATEGORIES))
        // The hand-added sub category survives alongside the seeded ones, and
        // the moved category stays where it was put rather than snapping back
        // to its seeded position.
        ->and($cardio->refresh()->position)->toBe(99)
        ->and($cardio->children()->count())->toBe($seeded + 1)
        ->and($cardio->children()->pluck('name')->all())->toContain('Custom Condition');
});
