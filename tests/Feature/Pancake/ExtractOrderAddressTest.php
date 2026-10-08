<?php

use App\Models\Order;
use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Modules\Pancake\Models\Commune;
use Modules\Pancake\Models\District;
use Modules\Pancake\Models\Province;
use Modules\Pancake\Support\GeoMatcher;

/**
 * Pancake Orders → "Get address": read the order's Messenger conversation, let
 * the model split out the address, and match it to Pancake's location ids.
 * Read-only — nothing goes back to Pancake.
 */
function seedGeo(): void
{
    Province::create(['id' => '63_108', 'country_code' => 63, 'name' => 'Batangas', 'name_en' => 'Batangas']);
    Province::create(['id' => '63_mm', 'country_code' => 63, 'name' => 'Metro-manila', 'name_en' => 'Metro manila']);
    Province::create(['id' => '63_qz', 'country_code' => 63, 'name' => 'Quezon', 'name_en' => 'Quezon']);

    District::create(['id' => '63_108_lipa', 'province_id' => '63_108', 'name' => 'Lipa-city', 'name_en' => 'Lipa']);
    District::create(['id' => '63_108_sj', 'province_id' => '63_108', 'name' => 'Batangas-san-juan', 'name_en' => 'san juan']);
    District::create(['id' => '63_mm_qc', 'province_id' => '63_mm', 'name' => 'Quezon-city', 'name_en' => 'Quezon city']);
    District::create(['id' => '63_mm_sj', 'province_id' => '63_mm', 'name' => 'Metro-manila-san-juan', 'name_en' => 'san juan']);
    District::create(['id' => '63_qz_luc', 'province_id' => '63_qz', 'name' => 'Lucena-city', 'name_en' => 'Lucena city']);

    Commune::create(['id' => '63_108_lipa_1', 'province_id' => '63_108', 'district_id' => '63_108_lipa', 'name' => 'Sabang', 'name_en' => 'Sabang']);
    Commune::create(['id' => '63_108_lipa_2', 'province_id' => '63_108', 'district_id' => '63_108_lipa', 'name' => 'Poblacion barangay 7', 'name_en' => 'Poblacion barangay 7']);
    Commune::create(['id' => '63_mm_qc_1', 'province_id' => '63_mm', 'district_id' => '63_mm_qc', 'name' => 'Santa lucia', 'name_en' => 'Santa lucia']);
    Commune::create(['id' => '63_mm_sj_1', 'province_id' => '63_mm', 'district_id' => '63_mm_sj', 'name' => 'Greenhills', 'name_en' => 'Greenhills']);
}

function aiAnswer(array $overrides = []): array
{
    $answer = array_merge([
        'found' => true,
        'address_text' => 'Purok 3, Brgy Sabang, Lipa City, Batangas',
        'street' => 'Purok 3',
        'barangay' => 'Sabang',
        'city' => 'Lipa City',
        'province' => 'Batangas',
        'landmark' => 'near the chapel',
        'confidence' => 0.92,
    ], $overrides);

    return ['choices' => [['message' => ['content' => json_encode($answer)], 'finish_reason' => 'stop']]];
}

function orderWithConversation(Workspace $workspace, ?string $token = 'page-token'): Order
{
    $page = Page::factory()->forWorkspace($workspace)->create(['pancake_token' => $token]);

    return Order::factory()->forPage($page)->create(['fb_id' => $page->id.'_555']);
}

function fakeConversationAndAi(Order $order, array $ai = []): void
{
    Http::fake([
        'pages.fm/api/public_api/v1/pages/*' => Http::response(['success' => true, 'messages' => [
            ['from' => ['id' => (string) $order->page_id], 'message' => '<div>Hi po! Pa send po ng complete address.</div>', 'inserted_at' => '2026-10-08T01:00:00'],
            ['from' => ['id' => '555'], 'original_message' => 'Juan Dela Cruz 09171234567 Purok 3, Brgy Sabang, Lipa City, Batangas', 'inserted_at' => '2026-10-08T01:05:00'],
        ]]),
        'openrouter.ai/*' => Http::response(aiAnswer($ai)),
    ]);
}

beforeEach(function () {
    config(['openrouter.api_key' => 'test-key']);
    seedGeo();
});

it('reads the address from the conversation and matches every level', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = orderWithConversation($workspace);
    fakeConversationAndAi($order);

    $response = $this->postJson(route('workspaces.pancake.orders.extract-address', [$workspace, $order->id]))
        ->assertOk()
        ->json();

    expect($response['status'])->toBe('complete')
        ->and($response['province']['id'])->toBe('63_108')
        ->and($response['district']['id'])->toBe('63_108_lipa')
        ->and($response['district']['typed'])->toBe('Lipa City')
        ->and($response['commune']['id'])->toBe('63_108_lipa_1')
        ->and($response['address'])->toBe('Purok 3, near the chapel')
        ->and($response['formatted_address'])->toBe('Purok 3, near the chapel, Sabang, Lipa-city, Batangas');

    // The page's own message went to the model as "Page:", the customer's as "Customer:".
    Http::assertSent(fn ($request) => str_contains($request->url(), 'openrouter.ai')
        && str_contains($request['messages'][1]['content'], 'Page: Hi po!')
        && str_contains($request['messages'][1]['content'], 'Customer: Juan Dela Cruz'));
});

it('says so when the page has no Pancake token', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = orderWithConversation($workspace, token: null);
    Http::fake();

    $this->postJson(route('workspaces.pancake.orders.extract-address', [$workspace, $order->id]))
        ->assertStatus(422)
        ->assertJsonPath('message', "This order's page has no Pancake token. Add it on the Pages screen first.");

    Http::assertNothingSent();
});

it('returns not_found when the customer never gave an address', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = orderWithConversation($workspace);
    fakeConversationAndAi($order, ['found' => false, 'address_text' => '', 'street' => '', 'barangay' => '', 'city' => '', 'province' => '', 'landmark' => '', 'confidence' => 0]);

    $this->postJson(route('workspaces.pancake.orders.extract-address', [$workspace, $order->id]))
        ->assertOk()
        ->assertJsonPath('status', 'not_found')
        ->assertJsonPath('formatted_address', '');
});

it('will not read an order from another workspace', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = orderWithConversation($other);
    Http::fake();

    $this->postJson(route('workspaces.pancake.orders.extract-address', [$workspace, $order->id]))
        ->assertNotFound();
});

describe('GeoMatcher', function () {
    it('handles abbreviations, the "city" suffix and roman numerals', function () {
        $m = app(GeoMatcher::class);

        // "QC" matches no city, but the barangay exists in only one district.
        expect($m->match('NCR', 'QC', 'Sta. Lucia')['district']?->id)->toBe('63_mm_qc');

        $qc = $m->match('Metro Manila', 'Quezon City', 'Sta. Lucia');
        expect($qc['status'])->toBe('complete')
            ->and($qc['commune']->id)->toBe('63_mm_qc_1');

        expect($m->match('Batangas', 'Lipa', 'Poblacion Barangay VII')['commune']?->id)->toBe('63_108_lipa_2');
    });

    it('works out the province from a city that only one province has', function () {
        $result = app(GeoMatcher::class)->match('', 'Lucena City', '');

        expect($result['province']->id)->toBe('63_qz')
            ->and($result['district']->id)->toBe('63_qz_luc');
    });

    it('leaves a city empty when two provinces share the name and none is given', function () {
        $result = app(GeoMatcher::class)->match('', 'San Juan', '');

        expect($result['district'])->toBeNull()
            ->and($result['status'])->toBe('not_found');
    });

    it('finds the district from the barangay when the city is missing', function () {
        $result = app(GeoMatcher::class)->match('Metro Manila', '', 'Greenhills');

        expect($result['status'])->toBe('complete')
            ->and($result['district']->id)->toBe('63_mm_sj');
    });
});
