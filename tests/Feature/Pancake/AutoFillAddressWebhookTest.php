<?php

use App\Models\Page;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Pancake\Jobs\AutoFillOrderAddress;
use Modules\Pancake\Models\AddressAutofill;
use Modules\Pancake\Models\Commune;
use Modules\Pancake\Models\District;
use Modules\Pancake\Models\Province;
use Modules\Pancake\Support\GeoMatcher;

/**
 * Pancake's order webhook → AutoFillOrderAddress: new orders from shops with
 * auto-fill on get their address read from the chat and written back.
 */
function autofillShop(array $attributes = []): Shop
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    return Shop::factory()->forWorkspace($workspace)->create([
        'pos_token' => 'pos-123',
        'auto_fill_address' => true,
        'webhook_secret' => 'shh-secret',
        ...$attributes,
    ]);
}

function newOrderPayload(Shop $shop, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 9001,
        'shop_id' => $shop->id,
        'page_id' => 777,
        'conversation_id' => '777_555',
        'status' => 0,
        'shipping_address' => [
            'full_name' => 'Juan Dela Cruz',
            'phone_number' => '09171234567',
            'commune_id' => null,
        ],
    ], $overrides);
}

function postWebhook(Shop $shop, array $body, ?string $secret = 'shh-secret')
{
    return test()->postJson(
        route('api.webhooks.pancake.orders', $shop),
        $body,
        $secret ? ['X-Artemis-Secret' => $secret] : [],
    );
}

function autofillAiAnswer(array $overrides = []): array
{
    return ['choices' => [['message' => ['content' => json_encode([
        'found' => true,
        'address_text' => 'Purok 3, Brgy Sabang, Lipa City, Batangas',
        'street' => 'Purok 3',
        'barangay' => 'Sabang',
        'city' => 'Lipa City',
        'province' => 'Batangas',
        'landmark' => 'near the chapel',
        'confidence' => 0.92,
        ...$overrides,
    ])]]], 'usage' => ['prompt_tokens' => 2840, 'completion_tokens' => 120, 'total_tokens' => 2960, 'cost' => 0.000498]];
}

/**
 * A queued record plus the page, chat, AI and Pancake the job will hit.
 *
 * @param  array  $ai  overrides for the extraction answer
 * @param  array  $orderNow  overrides for the order as Pancake returns it when the job reloads it
 * @param  ?array  $chat  the conversation; defaults to one message holding the address
 * @param  string  $pick  the shortlist call's answer ('' = none)
 */
function queuedAutofill(Shop $shop, array $ai = [], int $posStatus = 200, array $orderNow = [], ?array $chat = null, string $pick = ''): AddressAutofill
{
    Page::factory()->forWorkspace($shop->workspace)->create(['id' => 777, 'pancake_token' => 'page-token']);

    Province::create(['id' => '63_108', 'country_code' => 63, 'name' => 'Batangas', 'name_en' => 'Batangas']);
    District::create(['id' => '63_108_lipa', 'province_id' => '63_108', 'name' => 'Lipa-city', 'name_en' => 'Lipa']);
    Commune::create(['id' => '63_108_lipa_1', 'province_id' => '63_108', 'district_id' => '63_108_lipa', 'name' => 'Sabang', 'name_en' => 'Sabang']);
    Commune::create(['id' => '63_108_lipa_2', 'province_id' => '63_108', 'district_id' => '63_108_lipa', 'name' => 'Marawoy', 'name_en' => 'Marawoy']);
    app(GeoMatcher::class)->refreshSearchKeys();

    $chat ??= [
        ['from' => ['id' => '777'], 'original_message' => 'Hi po! Pa send po ng address.', 'inserted_at' => '2026-10-08T01:00:00'],
        ['from' => ['id' => '555'], 'original_message' => 'Purok 3, Brgy Sabang, Lipa City, Batangas', 'inserted_at' => '2026-10-08T01:05:00'],
    ];

    Http::fake(function ($request) use ($shop, $ai, $posStatus, $orderNow, $chat, $pick) {
        $url = $request->url();

        if (str_contains($url, 'pos.pages.fm')) {
            return $request->method() === 'GET'
                ? Http::response(['success' => true, 'data' => array_replace_recursive(newOrderPayload($shop), $orderNow)])
                : Http::response(['success' => $posStatus === 200], $posStatus);
        }

        if (str_contains($url, 'pages.fm/api/public_api')) {
            return Http::response(['messages' => $chat]);
        }

        if (str_contains($url, 'openrouter.ai')) {
            return data_get($request->data(), 'response_format.json_schema.name') === 'pick_place'
                ? Http::response(['choices' => [['message' => ['content' => json_encode(['id' => $pick])]]], 'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 10, 'cost' => 0.0001]])
                : Http::response(autofillAiAnswer($ai));
        }

        return Http::response([], 404);
    });

    return AddressAutofill::create([
        'shop_id' => $shop->id,
        'pancake_order_id' => '9001',
        'page_id' => '777',
        'conversation_id' => '777_555',
        'status' => AddressAutofill::QUEUED,
        'payload' => newOrderPayload($shop),
    ]);
}

function runAutofill(AddressAutofill $record): AddressAutofill
{
    app()->call([new AutoFillOrderAddress($record), 'handle']);

    return $record->fresh();
}

beforeEach(function () {
    config(['openrouter.api_key' => 'test-key']);
});

describe('webhook', function () {
    beforeEach(fn () => Queue::fake());

    it('queues a new order with a conversation and no address', function () {
        $shop = autofillShop();

        postWebhook($shop, newOrderPayload($shop))->assertOk()->assertJson(['status' => 'queued']);

        $record = AddressAutofill::sole();
        expect($record)
            ->status->toBe(AddressAutofill::QUEUED)
            ->pancake_order_id->toBe('9001')
            ->conversation_id->toBe('777_555');

        // Read a few minutes later, not straight away.
        Queue::assertPushed(AutoFillOrderAddress::class, fn ($job) => $job->record->is($record)
            && $job->delay->between(now()->addMinutes(4), now()->addMinutes(6)));
    });

    it('accepts the order wrapped in data', function () {
        $shop = autofillShop();

        postWebhook($shop, ['data' => newOrderPayload($shop)])->assertJson(['status' => 'queued']);
    });

    it('rejects a missing or wrong secret', function () {
        $shop = autofillShop();

        postWebhook($shop, newOrderPayload($shop), secret: null)->assertUnauthorized();
        postWebhook($shop, newOrderPayload($shop), secret: 'wrong')->assertUnauthorized();

        Queue::assertNothingPushed();
    });

    it('ignores, with a 200, orders it has nothing to do with', function (array $shopAttributes, array $order, string $reason) {
        $shop = autofillShop($shopAttributes);

        postWebhook($shop, newOrderPayload($shop, $order))
            ->assertOk()
            ->assertJson(['status' => 'ignored', 'reason' => $reason]);

        Queue::assertNothingPushed();
        expect(AddressAutofill::count())->toBe(0);
    })->with([
        'auto-fill off' => [['auto_fill_address' => false], [], 'Auto-fill is off for this shop.'],
        'not new' => [[], ['status' => 1], 'Not a new order.'],
        'already has a commune' => [[], ['shipping_address' => ['commune_id' => '63_1']], 'Order already has a full address.'],
        'no conversation' => [[], ['conversation_id' => ''], 'Order has no Messenger conversation.'],
    ]);

    it('handles an order once, but tries again after no_address', function () {
        $shop = autofillShop();

        postWebhook($shop, newOrderPayload($shop))->assertJson(['status' => 'queued']);
        postWebhook($shop, newOrderPayload($shop))->assertJson(['status' => 'ignored', 'reason' => 'Already handled.']);

        AddressAutofill::sole()->update(['status' => AddressAutofill::NO_ADDRESS]);

        postWebhook($shop, newOrderPayload($shop))->assertJson(['status' => 'queued']);

        Queue::assertPushed(AutoFillOrderAddress::class, 2);
        expect(AddressAutofill::count())->toBe(1);
    });
});

describe('job', function () {
    // The retry is dispatched from inside the job; faked so it does not run inline.
    beforeEach(fn () => Queue::fake());

    it('records what it would send in dry-run, without calling Pancake', function () {
        config(['pancake.auto_fill_address.dry_run' => true]);
        $record = runAutofill(queuedAutofill(autofillShop()));

        expect($record)
            ->ai_cost_usd->toBe(0.000498)
            ->input_tokens->toBe(2840)
            ->output_tokens->toBe(120);

        // OpenRouter is asked to report the cost.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'openrouter.ai')
            && $request['usage'] === ['include' => true]);

        expect($record->status)->toBe(AddressAutofill::DRY_RUN)
            // Stored as JSON, which does not keep key order.
            ->and($record->result['would_send']['shipping_address'])->toEqualCanonicalizing([
                'full_name' => 'Juan Dela Cruz',
                'phone_number' => '09171234567',
                'address' => 'Purok 3, near the chapel',
                'province_id' => '63_108',
                'district_id' => '63_108_lipa',
                'commune_id' => '63_108_lipa_1',
            ]);

        Http::assertNotSent(fn ($request) => $request->method() === 'PUT');
    });

    it('writes the address back to Pancake when dry-run is off', function () {
        config(['pancake.auto_fill_address.dry_run' => false]);
        $shop = autofillShop();
        $record = runAutofill(queuedAutofill($shop));

        expect($record->status)->toBe(AddressAutofill::UPDATED);

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), "pos.pages.fm/api/v1/shops/{$shop->id}/orders/9001")
            && str_contains($request->url(), 'api_key=pos-123')
            && $request['shipping_address']['commune_id'] === '63_108_lipa_1'
            && $request['shipping_address']['phone_number'] === '09171234567');
    });

    it('leaves it for review when a level did not match', function () {
        config(['pancake.auto_fill_address.dry_run' => false]);
        $record = runAutofill(queuedAutofill(autofillShop(), ['barangay' => 'Nowhere']));

        expect($record->status)->toBe(AddressAutofill::NEEDS_REVIEW)
            ->and($record->reason)->toBe('Not every level matched a Pancake location.');

        // The shortlist check was asked, and its answer ("none") respected.
        Http::assertSent(fn ($request) => data_get($request->data(), 'response_format.json_schema.name') === 'pick_place'
            && str_contains($request['messages'][1]['content'], '63_108_lipa_2: Marawoy'));

        Http::assertNotSent(fn ($request) => $request->method() === 'PUT');
    });

    it('leaves it for review when the AI is unsure', function () {
        config(['pancake.auto_fill_address.dry_run' => false]);
        $record = runAutofill(queuedAutofill(autofillShop(), ['confidence' => 0.4]));

        expect($record->status)->toBe(AddressAutofill::NEEDS_REVIEW);
        Http::assertNotSent(fn ($request) => $request->method() === 'PUT');
    });

    it('reads again later when there is no address yet, then settles on no_address', function () {
        $record = runAutofill(queuedAutofill(autofillShop(), ['found' => false, 'confidence' => 0]));

        expect($record)
            ->status->toBe(AddressAutofill::QUEUED)
            ->attempts->toBe(1)
            ->reason->toContain('Reading again at');
        Queue::assertPushed(AutoFillOrderAddress::class, fn ($job) => $job->delay->between(now()->addMinutes(24), now()->addMinutes(26)));

        $record = runAutofill($record);

        // Two reads, two AI calls: the cost adds up.
        expect($record)
            ->status->toBe(AddressAutofill::NO_ADDRESS)
            ->attempts->toBe(2)
            ->ai_cost_usd->toBe(0.000996);
    });

    it('does not call the AI when no customer message looks like an address', function () {
        $record = runAutofill(queuedAutofill(autofillShop(), chat: [
            ['from' => ['id' => '777'], 'original_message' => 'Hello po! Ano po order nila?', 'inserted_at' => '2026-10-08T01:00:00'],
            ['from' => ['id' => '555'], 'original_message' => 'Magkano po 2 box?', 'inserted_at' => '2026-10-08T01:05:00'],
        ]));

        expect($record)
            ->status->toBe(AddressAutofill::QUEUED)
            ->reason->toContain('No message in the chat looks like an address')
            ->ai_cost_usd->toBeNull();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openrouter.ai'));
    });

    it('sends the AI only the messages that look like an address', function () {
        runAutofill(queuedAutofill(autofillShop(), chat: [
            ['from' => ['id' => '555'], 'original_message' => 'Magkano po?', 'inserted_at' => '2026-10-08T00:50:00'],
            ['from' => ['id' => '777'], 'original_message' => '499 po. Pa send po ng complete address.', 'inserted_at' => '2026-10-08T01:00:00'],
            ['from' => ['id' => '555'], 'original_message' => 'Purok 3, Brgy Sabang, Lipa City', 'inserted_at' => '2026-10-08T01:05:00'],
            ['from' => ['id' => '555'], 'original_message' => 'Salamat po', 'inserted_at' => '2026-10-08T01:06:00'],
        ]));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'openrouter.ai')
            && str_contains($request['messages'][1]['content'], 'Customer: Purok 3')
            && str_contains($request['messages'][1]['content'], 'Page: 499 po')
            && ! str_contains($request['messages'][1]['content'], 'Magkano')
            && ! str_contains($request['messages'][1]['content'], 'Salamat'));
    });

    it('fills a level from the shortlist when the name did not match', function () {
        config(['pancake.auto_fill_address.dry_run' => true]);
        $record = runAutofill(queuedAutofill(autofillShop(), ['barangay' => 'Marawoi Proper'], pick: '63_108_lipa_2'));

        expect($record->status)->toBe(AddressAutofill::DRY_RUN)
            ->and($record->result['commune']['id'])->toBe('63_108_lipa_2')
            ->and($record->result['picked_by_ai'])->toBe(['commune'])
            // Both AI calls are counted.
            ->and($record->ai_cost_usd)->toBe(0.000598);
    });

    it('skips an order that is no longer new or got its address meanwhile', function (array $orderNow, string $reason) {
        $record = runAutofill(queuedAutofill(autofillShop(), orderNow: $orderNow));

        expect($record)->status->toBe(AddressAutofill::SKIPPED)->reason->toBe($reason);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openrouter.ai'));
    })->with([
        'confirmed' => [['status' => 1], 'The order is no longer new.'],
        'address filled' => [['shipping_address' => ['commune_id' => '63_1']], 'The address was filled in meanwhile.'],
    ]);

    it('builds the update on the order as Pancake has it now', function () {
        config(['pancake.auto_fill_address.dry_run' => false]);
        runAutofill(queuedAutofill(autofillShop(), orderNow: ['shipping_address' => ['phone_number' => '09998887777']]));

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request['shipping_address']['phone_number'] === '09998887777');
    });

    it('skips when the page has no Pancake token', function () {
        $shop = autofillShop();
        $record = queuedAutofill($shop);
        Page::find(777)->update(['pancake_token' => null]);

        expect(runAutofill($record))
            ->status->toBe(AddressAutofill::SKIPPED)
            ->reason->toContain('no Pancake token')
            ->ai_cost_usd->toBeNull();
    });

    it('records a failure when Pancake refuses the update', function () {
        config(['pancake.auto_fill_address.dry_run' => false]);
        $record = queuedAutofill(autofillShop(), posStatus: 422);

        expect(runAutofill($record)->status)->toBe(AddressAutofill::FAILED);
    });
});
