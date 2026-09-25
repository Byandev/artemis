<?php

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Products\Models\ProductForm;
use Modules\Products\Models\ProductResearch;
use Modules\Products\Models\TargetMarket;
use Tests\TestCase;

// Module test dirs aren't bound by the root tests/Pest.php (->in('Feature') only
// covers tests/Feature), so extend the app TestCase explicitly to boot the app.
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Nothing in this file may reach the network. Deliberately no Http::fake()
    // here: stubs merge and the first match wins, so a default set up in
    // beforeEach would quietly outrank the failure cases below — the same
    // reasoning as tests/Feature/Settings/WelleIntegrationTest.php.
    Http::preventStrayRequests();

    // Every OpenRouter setting is pinned, not just the key. These tests assert
    // the outbound request, and left ambient they would carry whatever model,
    // URL and attribution the developer's .env happens to set.
    config([
        'openrouter.api_key' => 'sk-test',
        'openrouter.product_research_model' => 'openai/gpt-4o-mini',
        'openrouter.base_uri' => 'https://openrouter.ai/api/v1',
        'openrouter.referer' => null,
        'openrouter.title' => null,
    ]);
});

/**
 * A workspace with Balm + Musculoskeletal > Back Pain — the pairing the design
 * the builder was built from uses.
 *
 * @return array{user: User, workspace: Workspace, form: ProductForm, category: TargetMarket, sub: TargetMarket}
 */
function suggestionFixtures(): array
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

/**
 * A chat-completions body shaped the way the strict json_schema pins it.
 */
function fakeSuggestionBody(int $count = 10): array
{
    $names = [];

    foreach (range(1, $count) as $n) {
        $names[] = ['name' => "Name {$n}", 'rationale' => "Reason {$n}"];
    }

    return [
        'choices' => [[
            'finish_reason' => 'stop',
            'message' => [
                'content' => json_encode([
                    'positioning' => 'Fast-acting topical relief.',
                    'names' => $names,
                ]),
            ],
        ]],
    ];
}

test('it returns a positioning line and ten names', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category, 'sub' => $sub] = suggestionFixtures();

    Http::fake(['openrouter.ai/*' => Http::response(fakeSuggestionBody())]);

    $response = $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'target_market_sub_id' => $sub->id,
        ])
        ->assertOk();

    expect($response->json('positioning'))->toBe('Fast-acting topical relief.')
        ->and($response->json('names'))->toHaveCount(10)
        ->and($response->json('names.0'))->toBe(['name' => 'Name 1', 'rationale' => 'Reason 1']);
});

test('the outbound call carries the key, the model and the strict schema', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category, 'sub' => $sub] = suggestionFixtures();

    Http::fake(['openrouter.ai/*' => Http::response(fakeSuggestionBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'target_market_sub_id' => $sub->id,
        ])
        ->assertOk();

    Http::assertSent(function ($request) {
        $brief = $request['messages'][1]['content'];

        return $request->hasHeader('Authorization', 'Bearer sk-test')
            && str_ends_with($request->url(), '/chat/completions')
            && $request['model'] === 'openai/gpt-4o-mini'
            // Without strict structured output the grid would be parsing prose.
            && $request['response_format']['type'] === 'json_schema'
            && $request['response_format']['json_schema']['strict'] === true
            // The taxonomy reaches the prompt by name; ids never do.
            && str_contains($brief, 'Balm')
            && str_contains($brief, 'Musculoskeletal')
            && str_contains($brief, 'Back Pain');
    });
});

test('a brief with no sub category still generates', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    Http::fake(['openrouter.ai/*' => Http::response(fakeSuggestionBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertOk();

    Http::assertSent(fn ($request) => ! str_contains($request['messages'][1]['content'], 'Sub category'));
});

test('with no api key it says so and never calls the provider', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    // A deploy that has config/openrouter.php but no OPEN_ROUTER_API_KEY.
    config(['openrouter.api_key' => null]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertStatus(503)
        ->assertJsonPath('message', "Name suggestions aren't configured yet. Set OPEN_ROUTER_API_KEY to switch this on.");

    Http::assertNothingSent();
});

test('an upstream failure is reported without leaking the provider error', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'secret upstream detail']], 500)]);

    $response = $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertStatus(502);

    // An upstream error body can echo the prompt back, and the prompt carries
    // the workspace's brief — so none of it reaches the browser.
    expect($response->json('message'))->not->toContain('secret upstream detail');
});

test('a throttled provider asks the user to wait rather than to retry now', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    Http::fake(['openrouter.ai/*' => Http::response([], 429)]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The name generator is busy right now. Try again in a moment.');
});

test('a timeout is reported rather than surfacing as a crash', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    Http::fake(fn () => throw new ConnectionException('timed out'));

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The name generator could not be reached. Try again in a moment.');
});

test('a 200 carrying an unusable answer is refused, not half-rendered', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    // A refusal or a truncated answer still arrives as a 200.
    Http::fake(['openrouter.ai/*' => Http::response([
        'choices' => [['message' => ['content' => 'I cannot help with that.']]],
    ])]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The name generator returned something unusable. Try again.');
});

test('fewer names than asked for are still shown', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    // The grid wraps, so a short set beats an error the user can do nothing
    // about.
    Http::fake(['openrouter.ai/*' => Http::response(fakeSuggestionBody(4))]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertOk()
        ->assertJsonCount(4, 'names');
});

test('generating needs the manage permission, not just view', function () {
    ['workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    // Every press is a paid call, so someone who may only read the list must
    // not be able to spend against the workspace's account.
    $viewer = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewProductResearch->value],
        'Products',
    );

    $this->actingAs($viewer)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertForbidden();

    Http::assertNothingSent();
});

test('it 404s for a workspace without the products module', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // Owners hold '*', so the permission check alone would wave them through.
    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [])
        ->assertNotFound();

    Http::assertNothingSent();
});

test('it refuses a brief that saving would refuse', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category, 'sub' => $sub] = suggestionFixtures();
    ['workspace' => $other] = makeProductsWorkspace();

    $foreignForm = ProductForm::create(['workspace_id' => $other->id, 'name' => 'Spray']);
    $otherCategory = TargetMarket::create(['workspace_id' => $workspace->id, 'name' => 'Respiratory']);

    // Missing entirely.
    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [])
        ->assertJsonValidationErrors(['product_form_id', 'target_market_id']);

    // A form from another workspace.
    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $foreignForm->id,
            'target_market_id' => $category->id,
        ])
        ->assertJsonValidationErrors('product_form_id');

    // A sub category that does not belong to the market beside it — the
    // generator must not be handed a pairing the brief could not be saved with.
    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $otherCategory->id,
            'target_market_sub_id' => $sub->id,
        ])
        ->assertJsonValidationErrors('target_market_sub_id');

    // A sub category in the market slot.
    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $sub->id,
        ])
        ->assertJsonValidationErrors('target_market_id');

    Http::assertNothingSent();
});

test('it talks to OpenRouter with attribution and strict provider routing', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    config([
        'openrouter.referer' => 'https://artemis.test',
        'openrouter.title' => 'Artemis',
    ]);

    Http::fake(['openrouter.ai/*' => Http::response(fakeSuggestionBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertOk();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer sk-test')
            && $request['model'] === 'openai/gpt-4o-mini'
            // Attribution for the OpenRouter leaderboards.
            && $request->hasHeader('HTTP-Referer', 'https://artemis.test')
            && $request->hasHeader('X-OpenRouter-Title', 'Artemis')
            // One model is served by several provider endpoints and only some
            // honour a strict schema; without this the request can be routed to
            // one that cannot, and OpenRouter fails it outright.
            && $request['provider']['require_parameters'] === true;
    });
});

test('attribution headers are left off when they are not configured', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    Http::fake(['openrouter.ai/*' => Http::response(fakeSuggestionBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertOk();

    Http::assertSent(fn ($request) => ! $request->hasHeader('HTTP-Referer')
        && ! $request->hasHeader('X-OpenRouter-Title'));
});

test("the brief's prompt and count reach the model", function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    Http::fake(['openrouter.ai/*' => Http::response(fakeSuggestionBody(4))]);

    // Step 2 is where the name is chosen, so there is usually no brief yet —
    // the builder sends the pair it is holding rather than the server reading
    // it back off a row.
    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'naming_prompt' => 'You name premium herbal products for Cebu pharmacies.',
            'name_count' => 4,
        ])
        ->assertOk()
        ->assertJsonCount(4, 'names');

    Http::assertSent(function ($request) {
        $system = $request['messages'][0]['content'];

        return str_contains($system, 'Cebu pharmacies')
            // The count drives the schema, or the model would still return ten.
            && $request['response_format']['json_schema']['schema']['properties']['names']['minItems'] === 4
            && $request['response_format']['json_schema']['schema']['properties']['names']['maxItems'] === 4
            // The rules that keep the grid usable are appended, not editable.
            && str_contains($system, 'Never name a delivery form other than the one in the brief')
            && str_contains($system, 'Vary the angles hard');
    });
});

test('a brief that never opened the dialog generates on the defaults', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    Http::fake(['openrouter.ai/*' => Http::response(fakeSuggestionBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains(
        $request['messages'][0]['content'],
        ProductResearch::DEFAULT_PROMPT
    ));
});

test('the configure dialog is saved with the brief, not the workspace', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/product-research", [
            'name' => 'Back Ease Balm',
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'naming_prompt' => 'Short, punchy names only.',
            'name_count' => 6,
        ])
        ->assertRedirect();

    $productResearch = ProductResearch::ofWorkspace($workspace)->firstOrFail();

    expect($productResearch->naming_prompt)->toBe('Short, punchy names only.')
        ->and($productResearch->name_count)->toBe(6);
});

test('two briefs in one workspace keep their own prompts', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    $file = fn (string $name, string $prompt, int $count) => $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/product-research", [
            'name' => $name,
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'naming_prompt' => $prompt,
            'name_count' => $count,
        ])
        ->assertRedirect();

    $file('Back Ease Balm', 'Clinical, restrained names.', 3);
    $file('Joint Relief Spray', 'Loud, punchy names.', 8);

    $balm = ProductResearch::where('name', 'Back Ease Balm')->firstOrFail();
    $spray = ProductResearch::where('name', 'Joint Relief Spray')->firstOrFail();

    // The whole point of the move: one brief's wording does not follow the
    // other around the workspace.
    expect($balm->naming_prompt)->toBe('Clinical, restrained names.')
        ->and($balm->name_count)->toBe(3)
        ->and($spray->naming_prompt)->toBe('Loud, punchy names.')
        ->and($spray->name_count)->toBe(8);
});

test('resetting to the default stores nothing rather than a copy of it', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    $productResearch = ProductResearch::create([
        'workspace_id' => $workspace->id,
        'name' => 'Back Ease Balm',
        'product_form_id' => $form->id,
        'target_market_id' => $category->id,
        'naming_prompt' => 'Something custom.',
    ]);

    // Saving the default back has to clear the column, not store a copy that
    // would then never track a change to the default.
    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/product-research/{$productResearch->id}", [
            'name' => 'Back Ease Balm',
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'naming_prompt' => ProductResearch::DEFAULT_PROMPT,
            'name_count' => 10,
        ])
        ->assertRedirect();

    expect($productResearch->refresh()->naming_prompt)->toBeNull();
});

test('the count is capped, and saving needs the manage permission', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    foreach ([0, ProductResearch::MAX_COUNT + 1] as $bad) {
        $this->actingAs($owner)
            ->postJson("/workspaces/{$workspace->slug}/products/product-research", [
                'name' => 'Back Ease Balm',
                'product_form_id' => $form->id,
                'target_market_id' => $category->id,
                'name_count' => $bad,
            ])
            ->assertJsonValidationErrors('name_count');
    }

    // The generate endpoint validates it too — it is the one actually spending.
    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'name_count' => ProductResearch::MAX_COUNT + 1,
        ])
        ->assertJsonValidationErrors('name_count');

    $viewer = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewProductResearch->value],
        'Products',
    );

    $this->actingAs($viewer)
        ->postJson("/workspaces/{$workspace->slug}/products/product-research", [
            'name' => 'Back Ease Balm',
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'name_count' => 5,
        ])
        ->assertForbidden();
});

test('the builder opens the dialog on the brief it is editing', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    $productResearch = ProductResearch::create([
        'workspace_id' => $workspace->id,
        'name' => 'Back Ease Balm',
        'product_form_id' => $form->id,
        'target_market_id' => $category->id,
        'naming_prompt' => 'House voice.',
        'name_count' => 7,
    ]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/product-research/{$productResearch->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->where('promptSettings.naming_prompt', 'House voice.')
            ->where('promptSettings.name_count', 7)
            ->where('promptSettings.max_count', ProductResearch::MAX_COUNT)
            ->where('promptSettings.default_prompt', ProductResearch::DEFAULT_PROMPT)
            ->where('promptSettings.is_default', false)
        );

    // A brief that does not exist yet opens on the defaults, whatever any
    // other brief in the workspace was set to.
    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/product-research/create")
        ->assertInertia(fn ($page) => $page
            ->where('promptSettings.naming_prompt', ProductResearch::DEFAULT_PROMPT)
            ->where('promptSettings.name_count', ProductResearch::DEFAULT_COUNT)
            ->where('promptSettings.is_default', true)
        );
});

test('changing one half of the settings leaves the other alone', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    $productResearch = ProductResearch::create([
        'workspace_id' => $workspace->id,
        'name' => 'Back Ease Balm',
        'product_form_id' => $form->id,
        'target_market_id' => $category->id,
        'naming_prompt' => 'House voice.',
        'name_count' => 7,
        'packshot_prompt' => 'Watercolour on cream paper.',
        'packshot_count' => 3,
    ]);

    // The naming half only — the packshot settings are not restated.
    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/product-research/{$productResearch->id}", [
            'name' => 'Back Ease Balm',
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'naming_prompt' => 'New voice.',
            'name_count' => 4,
        ])
        ->assertRedirect();

    $productResearch->refresh();

    expect($productResearch->naming_prompt)->toBe('New voice.')
        ->and($productResearch->name_count)->toBe(4)
        // Left out, so left as they were rather than wiped.
        ->and($productResearch->packshot_prompt)->toBe('Watercolour on cream paper.')
        ->and($productResearch->packshot_count)->toBe(3);
});
