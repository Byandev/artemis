<?php

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Products\Models\ProductForm;
use Modules\Products\Models\RdpPromptSetting;
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

    // Every gateway setting is pinned, not just the key. These tests assert the
    // outbound request, and .env can point the app at a gateway — which it does
    // on a machine configured for OpenRouter. Left ambient, the OpenAI cases
    // below would fire at whatever the developer happens to be using.
    config([
        'openai.api_key' => 'sk-test',
        'openai.rdp_model' => 'gpt-4o-mini',
        'openai.base_uri' => null,
        'openai.referer' => null,
        'openai.title' => null,
        'openai.require_provider_parameters' => false,
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

    Http::fake(['api.openai.com/*' => Http::response(fakeSuggestionBody())]);

    $response = $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
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

    Http::fake(['api.openai.com/*' => Http::response(fakeSuggestionBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
            'target_market_sub_id' => $sub->id,
        ])
        ->assertOk();

    Http::assertSent(function ($request) {
        $brief = $request['messages'][1]['content'];

        return $request->hasHeader('Authorization', 'Bearer sk-test')
            && str_ends_with($request->url(), '/chat/completions')
            && $request['model'] === 'gpt-4o-mini'
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

    Http::fake(['api.openai.com/*' => Http::response(fakeSuggestionBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertOk();

    Http::assertSent(fn ($request) => ! str_contains($request['messages'][1]['content'], 'Sub category'));
});

test('with no api key it says so and never calls the provider', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    // Today's state: config/openai.php exists but OPENAI_API_KEY is unset.
    config(['openai.api_key' => null]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertStatus(503)
        ->assertJsonPath('message', "Name suggestions aren't configured yet. Set OPENAI_API_KEY to switch this on.");

    Http::assertNothingSent();
});

test('an upstream failure is reported without leaking the provider error', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'secret upstream detail']], 500)]);

    $response = $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
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

    Http::fake(['api.openai.com/*' => Http::response([], 429)]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
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
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The name generator could not be reached. Try again in a moment.');
});

test('a 200 carrying an unusable answer is refused, not half-rendered', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    // A refusal or a truncated answer still arrives as a 200.
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'I cannot help with that.']]],
    ])]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
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
    Http::fake(['api.openai.com/*' => Http::response(fakeSuggestionBody(4))]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
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
        [Permission::ViewRdpBuilder->value],
        'Products',
    );

    $this->actingAs($viewer)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
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
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [])
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
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [])
        ->assertJsonValidationErrors(['product_form_id', 'target_market_id']);

    // A form from another workspace.
    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
            'product_form_id' => $foreignForm->id,
            'target_market_id' => $category->id,
        ])
        ->assertJsonValidationErrors('product_form_id');

    // A sub category that does not belong to the market beside it — the
    // generator must not be handed a pairing the brief could not be saved with.
    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $otherCategory->id,
            'target_market_sub_id' => $sub->id,
        ])
        ->assertJsonValidationErrors('target_market_sub_id');

    // A sub category in the market slot.
    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $sub->id,
        ])
        ->assertJsonValidationErrors('target_market_id');

    Http::assertNothingSent();
});

test('it talks to an OpenAI-compatible gateway when the base url points at one', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    // OpenRouter is a drop-in: same bearer auth, same /chat/completions shape,
    // namespaced model slugs.
    config([
        'openai.base_uri' => 'https://openrouter.ai/api/v1',
        'openai.rdp_model' => 'openai/gpt-4o-mini',
        'openai.referer' => 'https://artemis.test',
        'openai.title' => 'Artemis',
        'openai.require_provider_parameters' => true,
    ]);

    Http::fake(['openrouter.ai/*' => Http::response(fakeSuggestionBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
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

test('the provider routing key is left off for OpenAI, which rejects it', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    Http::fake(['api.openai.com/*' => Http::response(fakeSuggestionBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertOk();

    Http::assertSent(fn ($request) => ! isset($request['provider'])
        && ! $request->hasHeader('HTTP-Referer'));
});

test('the workspace prompt and count reach the model', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    RdpPromptSetting::create([
        'workspace_id' => $workspace->id,
        'naming_prompt' => 'You name premium herbal products for Cebu pharmacies.',
        'name_count' => 4,
    ]);

    Http::fake(['api.openai.com/*' => Http::response(fakeSuggestionBody(4))]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
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

test('a workspace that never opened the dialog generates on the defaults', function () {
    ['user' => $owner, 'workspace' => $workspace, 'form' => $form, 'category' => $category] = suggestionFixtures();

    Http::fake(['api.openai.com/*' => Http::response(fakeSuggestionBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/suggest-names", [
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains(
        $request['messages'][0]['content'],
        RdpPromptSetting::DEFAULT_PROMPT
    ));
});

test('the configure dialog saves the prompt and the count', function () {
    ['user' => $owner, 'workspace' => $workspace] = suggestionFixtures();

    $this->actingAs($owner)
        ->putJson("/workspaces/{$workspace->slug}/products/rdp-builder/prompt-settings", [
            'naming_prompt' => 'Short, punchy names only.',
            'name_count' => 6,
        ])
        ->assertOk()
        ->assertJsonPath('naming_prompt', 'Short, punchy names only.')
        ->assertJsonPath('name_count', 6)
        ->assertJsonPath('is_default', false);

    $settings = RdpPromptSetting::where('workspace_id', $workspace->id)->firstOrFail();
    expect($settings->naming_prompt)->toBe('Short, punchy names only.');
});

test('resetting to the default stores nothing rather than a copy of it', function () {
    ['user' => $owner, 'workspace' => $workspace] = suggestionFixtures();

    RdpPromptSetting::create([
        'workspace_id' => $workspace->id,
        'naming_prompt' => 'Something custom.',
        'name_count' => 10,
    ]);

    // Saving the default back has to clear the column, not store a copy that
    // would then never track a change to the default.
    $this->actingAs($owner)
        ->putJson("/workspaces/{$workspace->slug}/products/rdp-builder/prompt-settings", [
            'naming_prompt' => RdpPromptSetting::DEFAULT_PROMPT,
            'name_count' => 10,
        ])
        ->assertOk()
        ->assertJsonPath('is_default', true);

    expect(RdpPromptSetting::where('workspace_id', $workspace->id)->first()->naming_prompt)->toBeNull();
});

test('the count is capped, and saving needs the manage permission', function () {
    ['user' => $owner, 'workspace' => $workspace] = suggestionFixtures();

    foreach ([0, RdpPromptSetting::MAX_COUNT + 1] as $bad) {
        $this->actingAs($owner)
            ->putJson("/workspaces/{$workspace->slug}/products/rdp-builder/prompt-settings", [
                'name_count' => $bad,
            ])
            ->assertJsonValidationErrors('name_count');
    }

    $viewer = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewRdpBuilder->value],
        'Products',
    );

    $this->actingAs($viewer)
        ->putJson("/workspaces/{$workspace->slug}/products/rdp-builder/prompt-settings", [
            'name_count' => 5,
        ])
        ->assertForbidden();
});

test('the builder page opens the dialog on the saved settings', function () {
    ['user' => $owner, 'workspace' => $workspace] = suggestionFixtures();

    RdpPromptSetting::create([
        'workspace_id' => $workspace->id,
        'naming_prompt' => 'House voice.',
        'name_count' => 7,
    ]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder/create")
        ->assertInertia(fn ($page) => $page
            ->where('promptSettings.naming_prompt', 'House voice.')
            ->where('promptSettings.name_count', 7)
            ->where('promptSettings.max_count', RdpPromptSetting::MAX_COUNT)
            ->where('promptSettings.default_prompt', RdpPromptSetting::DEFAULT_PROMPT)
            ->where('promptSettings.is_default', false)
        );
});

test('changing one half of the settings leaves the other alone', function () {
    ['user' => $owner, 'workspace' => $workspace] = suggestionFixtures();

    RdpPromptSetting::create([
        'workspace_id' => $workspace->id,
        'naming_prompt' => 'House voice.',
        'name_count' => 7,
        'packshot_prompt' => 'Watercolour on cream paper.',
        'packshot_count' => 3,
    ]);

    // The naming half only — the packshot settings are not restated.
    $this->actingAs($owner)
        ->putJson("/workspaces/{$workspace->slug}/products/rdp-builder/prompt-settings", [
            'naming_prompt' => 'New voice.',
            'name_count' => 4,
        ])
        ->assertOk()
        ->assertJsonPath('naming_prompt', 'New voice.')
        ->assertJsonPath('name_count', 4)
        // Left out, so left as they were rather than wiped.
        ->assertJsonPath('packshot_prompt', 'Watercolour on cream paper.')
        ->assertJsonPath('packshot_count', 3);
});
