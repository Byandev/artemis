<?php

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Products\Models\ProductForm;
use Modules\Products\Models\Rdp;
use Modules\Products\Models\RdpPromptSetting;
use Modules\Products\Models\TargetMarket;
use Tests\TestCase;

// Module test dirs aren't bound by the root tests/Pest.php (->in('Feature') only
// covers tests/Feature), so extend the app TestCase explicitly to boot the app.
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    // Pinned rather than left ambient: .env can point the app at a gateway, and
    // these tests assert the outbound request.
    config([
        'openai.api_key' => 'sk-test',
        'openai.base_uri' => null,
        'openai.referer' => null,
        'openai.title' => null,
        'openai.packshot_model' => 'openai/gpt-image-2',
        'filesystems.rdp_media_disk' => 's3',
    ]);

    Storage::fake('s3');
});

/**
 * A saved brief with a name, a form and a market — what Step 3 needs.
 *
 * @return array{user: User, workspace: Workspace, rdp: Rdp}
 */
function packshotFixtures(): array
{
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $form = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Spray']);
    $category = TargetMarket::create(['workspace_id' => $workspace->id, 'name' => 'Cardiovascular']);

    $rdp = Rdp::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'product_form_id' => $form->id,
        'target_market_id' => $category->id,
        'name' => 'Cardio Vitality Spray',
    ]);

    return compact('owner', 'workspace', 'rdp') + ['user' => $owner];
}

/**
 * One image, as a single images-endpoint call returns it.
 *
 * One per response rather than a set: the generator asks once per option
 * because `n` is not portable — seedream ignores it and answers with a single
 * image however many are requested.
 */
function fakePackshotBody(int $count = 1): array
{
    $png = base64_encode(UploadedFile::fake()->image('option.png')->get());

    return ['data' => array_fill(0, $count, ['b64_json' => $png, 'media_type' => 'image/png'])];
}

test('it draws the configured number of options and files them on the brief', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    $response = $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertOk();

    // Five options means five calls, each asking for one image.
    Http::assertSentCount(5);

    expect($response->json('packshot_options'))->toHaveCount(5)
        // Nothing is chosen until someone picks one.
        ->and($response->json('packshot'))->toBeNull()
        ->and($rdp->getMedia(Rdp::PACKSHOT_OPTIONS_COLLECTION))->toHaveCount(5);

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/images')
            && $request['model'] === 'openai/gpt-image-2'
            && $request['n'] === 1
            // Separately seeded, or the set comes back as one picture repeated.
            && isset($request['seed'])
            // Branded by default — the step exists to show the actual product.
            && str_contains($request['prompt'], 'a printed label carrying the name')
            && str_contains($request['prompt'], 'the product name reads exactly "Cardio Vitality Spray"')
            && str_contains($request['prompt'], 'Spray')
            && str_contains($request['prompt'], 'Cardiovascular');
    });
});

test('re-drawing replaces the previous set rather than piling up', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    RdpPromptSetting::create(['workspace_id' => $workspace->id, 'packshot_count' => 3]);

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($owner)
            ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
            ->assertOk();
    }

    // Two runs of three, not six — the grid shows one set, and the old files
    // would otherwise sit in the bucket unreachable.
    expect($rdp->refresh()->getMedia(Rdp::PACKSHOT_OPTIONS_COLLECTION))->toHaveCount(3);
});

test('the workspace style note and count reach the generator', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    RdpPromptSetting::create([
        'workspace_id' => $workspace->id,
        'packshot_prompt' => 'Watercolour illustration on cream paper.',
        'packshot_count' => 2,
    ]);

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertOk()
        ->assertJsonCount(2, 'packshot_options');

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => str_contains($request['prompt'], 'Watercolour illustration'));
});

test('picking an option sets the packshot and leaves the option in the grid', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);
    RdpPromptSetting::create(['workspace_id' => $workspace->id, 'packshot_count' => 3]);

    $options = $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->json('packshot_options');

    $response = $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/{$rdp->id}/packshot/select", [
            'media_id' => $options[1]['id'],
        ])
        ->assertOk();

    expect($response->json('packshot'))->not->toBeNull()
        // Copied, not moved, so a different option can still be picked after.
        ->and($response->json('packshot_options'))->toHaveCount(3);
});

test('an option belonging to another brief cannot be adopted', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();
    ['rdp' => $otherRdp] = packshotFixtures();

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    $otherRdp->addMediaFromString('not-really-an-image')
        ->usingFileName('theirs.png')
        ->toMediaCollection(Rdp::PACKSHOT_OPTIONS_COLLECTION);

    $theirs = $otherRdp->getFirstMedia(Rdp::PACKSHOT_OPTIONS_COLLECTION);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/{$rdp->id}/packshot/select", [
            'media_id' => $theirs->id,
        ])
        ->assertNotFound();
});

test('someone can upload their own render instead', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    $response = $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/rdp-builder/packshot", [
            'rdp_id' => $rdp->id,
            'packshot' => UploadedFile::fake()->image('render.png'),
        ])
        ->assertOk();

    expect($response->json('packshot'))->not->toBeNull();

    $media = $rdp->refresh()->getFirstMedia(Rdp::PACKSHOT_COLLECTION);
    expect($media->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($media->getPathRelativeToRoot());

    // No generator involved.
    Http::assertNothingSent();
});

test('uploading a second render replaces the first', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    foreach (['first.png', 'second.png'] as $name) {
        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->slug}/products/rdp-builder/packshot", [
                'rdp_id' => $rdp->id,
                'packshot' => UploadedFile::fake()->image($name),
            ])
            ->assertOk();
    }

    expect($rdp->refresh()->getMedia(Rdp::PACKSHOT_COLLECTION))->toHaveCount(1)
        ->and($rdp->getFirstMedia(Rdp::PACKSHOT_COLLECTION)->file_name)->toBe('second.png');
});

test('a non-image upload is refused', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/rdp-builder/packshot", [
            'rdp_id' => $rdp->id,
            'packshot' => UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'),
        ])
        ->assertSessionHasErrors('packshot');
});

test('with no api key it says so and never calls the provider', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    config(['openai.api_key' => null]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertStatus(503);

    Http::assertNothingSent();
});

test('an upstream failure is reported without leaking the provider error', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    Http::fake(['api.openai.com/*' => Http::response(['error' => 'secret upstream detail'], 500)]);

    $response = $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertStatus(502);

    expect($response->json('message'))->not->toContain('secret upstream detail')
        // A failed run leaves the previous set alone.
        ->and($rdp->refresh()->getMedia(Rdp::PACKSHOT_OPTIONS_COLLECTION))->toHaveCount(0);
});

test('a 200 carrying no usable image is refused', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    Http::fake(['api.openai.com/*' => Http::response(['data' => [['b64_json' => '']]])]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertStatus(502);
});

test('drawing needs the manage permission, and the module', function () {
    ['workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    $viewer = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewRdpBuilder->value],
        'Products',
    );

    $this->actingAs($viewer)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertForbidden();

    $workspace->update(['products_module_enabled' => false]);

    $this->actingAs($viewer)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertNotFound();

    Http::assertNothingSent();
});

test('a packshot is served through the app, and not across workspaces', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();
    ['user' => $stranger] = packshotFixtures();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/rdp-builder/packshot", [
            'rdp_id' => $rdp->id,
            'packshot' => UploadedFile::fake()->image('render.png'),
        ])->assertOk();

    $media = $rdp->refresh()->getFirstMedia(Rdp::PACKSHOT_COLLECTION);
    $url = "/workspaces/{$workspace->slug}/products/rdp-builder/{$rdp->id}/packshot/{$media->id}";

    // A bucket that can sign hands back a 302 to the short-lived URL.
    $this->actingAs($owner)->get($url)->assertRedirect();
    $this->actingAs($stranger)->get($url)->assertForbidden();
});

test('opening a saved brief carries its packshot into the builder', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/rdp-builder/packshot", [
            'rdp_id' => $rdp->id,
            'packshot' => UploadedFile::fake()->image('render.png'),
        ])->assertOk();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/rdp-builder/{$rdp->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->has('rdp.packshot')
            ->has('rdp.packshot_options')
        );
});

test('the prompt describes the delivery form, not a generic container', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    // No description typed, so the standard one for the name applies.
    $patch = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Patch']);
    $rdp->update(['product_form_id' => $patch->id]);

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertOk();

    Http::assertSent(function ($request) {
        $prompt = $request['prompt'];

        // A patch is not a container: asked for generically it comes back as a
        // bottle every time.
        return str_contains($prompt, 'adhesive transdermal patch')
            && str_contains($prompt, 'do not substitute a bottle, jar or any other container')
            && ! str_contains($prompt, 'dropper bottle');
    });
});

test('a form the catalog added later still gets a usable prompt', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    $lozenge = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Lozenge']);
    $rdp->update(['product_form_id' => $lozenge->id]);

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertOk();

    // Vaguer than a known form, but never wrong.
    Http::assertSent(fn ($request) => str_contains(
        $request['prompt'],
        'the product packaged as a lozenge'
    ));
});

test('the market sets the palette and the sub category says what it treats', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    $sub = TargetMarket::create([
        'workspace_id' => $workspace->id,
        'parent_id' => $rdp->target_market_id,
        'name' => 'Hypertension',
    ]);
    $rdp->update(['target_market_sub_id' => $sub->id]);

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertOk();

    Http::assertSent(function ($request) {
        $prompt = $request['prompt'];

        // Left to itself the model reaches for amber and brown whatever the
        // product treats, so the category has to name the colour.
        return str_contains($prompt, 'deep red and crimson')
            && str_contains($prompt, 'Hypertension within Cardiovascular')
            // And the options must not all come back the same shade.
            && str_contains($prompt, 'different');
    });
});

test('a market with no palette of its own still avoids the default brown', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    $odd = TargetMarket::create(['workspace_id' => $workspace->id, 'name' => 'Sleep']);
    $rdp->update(['target_market_id' => $odd->id, 'target_market_sub_id' => null]);

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains(
        $request['prompt'],
        'clean modern colours suited to the condition it treats'
    ));
});

test('an image prompt that asks for no label still gets the spelling guard', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    RdpPromptSetting::create([
        'workspace_id' => $workspace->id,
        'packshot_prompt' => 'Blank unbranded packaging, no label of any kind.',
    ]);

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertOk();

    Http::assertSent(function ($request) {
        $prompt = $request['prompt'];

        // The prompt decides whether there is a label; the appended line is
        // phrased to stay harmless when there is not.
        return str_contains($prompt, 'no label of any kind')
            && str_contains($prompt, 'Wherever the packaging carries wording');
    });
});

test('a model that ignores n still yields the full set', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    // seedream answers every call with exactly one image whatever `n` says.
    // Asking once per option is what makes the count portable.
    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody(1))]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertOk()
        ->assertJsonCount(5, 'packshot_options');

    Http::assertSentCount(5);
});

test('a partial failure still returns the options that came back', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    RdpPromptSetting::create(['workspace_id' => $workspace->id, 'packshot_count' => 4]);

    // Two of the four calls fail. A partial set beats an error the user can do
    // nothing about — the grid wraps.
    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;

        return $calls % 2 === 0
            ? Http::response([], 500)
            : Http::response(fakePackshotBody());
    });

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertOk()
        ->assertJsonCount(2, 'packshot_options');
});

test('every option is seeded differently so the set is not one image repeated', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertOk();

    $seeds = [];
    Http::assertSent(function ($request) use (&$seeds) {
        $seeds[] = $request['seed'];

        return true;
    });

    expect(array_unique($seeds))->toHaveCount(5);
});

test('a description typed on the form is what gets drawn', function () {
    ['user' => $owner, 'workspace' => $workspace, 'rdp' => $rdp] = packshotFixtures();

    // A workspace that packs its balm in a sachet rather than a tin has to be
    // able to say so, and be believed over the standard description.
    $balm = ProductForm::create([
        'workspace_id' => $workspace->id,
        'name' => 'Balm',
        'packshot_description' => 'a flat resealable sachet with a tear notch',
    ]);
    $rdp->update(['product_form_id' => $balm->id]);

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $rdp->id])
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request['prompt'], 'a flat resealable sachet with a tear notch')
        && ! str_contains($request['prompt'], 'screw-top balm tin'));
});

test('the packaging description is saved and edited on the product form', function () {
    ['user' => $owner, 'workspace' => $workspace] = packshotFixtures();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/forms", [
            'name' => 'Sachet',
            'packshot_description' => 'a small flat single-dose sachet',
        ])
        ->assertSessionHasNoErrors();

    $form = ProductForm::where('name', 'Sachet')->firstOrFail();
    expect($form->packshotDescription())->toBe('a small flat single-dose sachet');

    // Cleared, so it falls back rather than keeping the old wording.
    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/forms/{$form->id}", [
            'name' => 'Sachet',
            'packshot_description' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($form->refresh()->packshot_description)->toBeNull()
        ->and($form->packshotDescription())->toBe('the product packaged as a sachet');
});

test('a draft that has never been saved can still generate', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $form = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Spray']);
    $category = TargetMarket::create(['workspace_id' => $workspace->id, 'name' => 'Cardiovascular']);

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    // No rdp_id: the brief is filed on the way through, so Step 3 does not
    // have to wait on Save to RDPs.
    $response = $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", [
            'name' => 'Cardio Vitality Spray',
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->assertOk()
        ->assertJsonCount(5, 'packshot_options');

    $rdp = Rdp::ofWorkspace($workspace)->firstOrFail();

    // The id comes back so the builder knows what it is now editing.
    expect($response->json('rdp_id'))->toBe($rdp->id)
        ->and($rdp->name)->toBe('Cardio Vitality Spray')
        ->and($rdp->created_by)->toBe($owner->id);
});

test('a draft is filed once, not once per generate', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $form = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Spray']);
    $category = TargetMarket::create(['workspace_id' => $workspace->id, 'name' => 'Cardiovascular']);

    Http::fake(['api.openai.com/*' => Http::response(fakePackshotBody())]);

    $first = $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", [
            'name' => 'Cardio Vitality Spray',
            'product_form_id' => $form->id,
            'target_market_id' => $category->id,
        ])
        ->json('rdp_id');

    // The builder sends the id it was given back on the next press.
    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $first])
        ->assertOk();

    expect(Rdp::ofWorkspace($workspace)->count())->toBe(1);
});

test('an unsaved draft missing its brief is refused rather than half filed', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", [])
        ->assertJsonValidationErrors(['name', 'product_form_id', 'target_market_id']);

    expect(Rdp::ofWorkspace($workspace)->count())->toBe(0);
    Http::assertNothingSent();
});

test('an rdp_id from another workspace is refused', function () {
    ['user' => $owner, 'workspace' => $workspace] = packshotFixtures();
    ['rdp' => $foreign] = packshotFixtures();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/products/rdp-builder/packshots", ['rdp_id' => $foreign->id])
        ->assertNotFound();

    Http::assertNothingSent();
});
