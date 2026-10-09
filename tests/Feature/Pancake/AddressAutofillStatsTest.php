<?php

use App\Models\Shop;
use Illuminate\Support\Carbon;
use Modules\Pancake\Models\AddressAutofill;

/**
 * Pancake → Address Auto-fill: the numbers behind the stats page.
 */
function autofillRow(Shop $shop, string $status, array $attributes = []): AddressAutofill
{
    static $n = 0;

    return AddressAutofill::create([
        'shop_id' => $shop->id,
        'pancake_order_id' => (string) (5000 + ++$n),
        'status' => $status,
        ...$attributes,
    ]);
}

function statsPage($workspace, array $filter = [])
{
    return test()->get(route('workspaces.pancake.address-autofill.index', ['workspace' => $workspace, 'filter' => $filter]))
        ->assertOk()
        ->viewData('page')['props'];
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-08 12:00:00');
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);
    $this->workspace = $workspace;
    $this->shop = Shop::factory()->forWorkspace($workspace)->create();
});

it('adds up outcomes, rates and cost', function () {
    autofillRow($this->shop, AddressAutofill::UPDATED, ['ai_cost_usd' => 0.0003, 'result' => ['status' => 'complete', 'picked_by_ai' => ['commune']]]);
    autofillRow($this->shop, AddressAutofill::DRY_RUN, ['ai_cost_usd' => 0.0003, 'result' => ['status' => 'complete', 'picked_by_ai' => []]]);
    autofillRow($this->shop, AddressAutofill::NEEDS_REVIEW, [
        'ai_cost_usd' => 0.0004,
        'reason' => 'Not every level matched a Pancake location.',
        'result' => ['status' => 'partial', 'province' => ['id' => '63_1'], 'district' => ['id' => '63_1_2'], 'commune' => ['id' => null]],
    ]);
    autofillRow($this->shop, AddressAutofill::NO_ADDRESS, ['ai_cost_usd' => 0.0002]);
    autofillRow($this->shop, AddressAutofill::SKIPPED);

    $props = statsPage($this->workspace);
    $summary = $props['summary'];

    expect($summary['total'])->toBe(5)
        ->and($summary['filled'])->toBe(2)
        // 2 filled of 4 read (skipped is not counted as read).
        ->and($summary['fill_rate'])->toEqual(0.5)
        // 2 filled of 3 where the customer gave an address.
        ->and(round($summary['match_rate'], 4))->toEqual(0.6667)
        ->and(round($summary['cost_usd'], 6))->toEqual(0.0012)
        ->and($summary['ai_checks'])->toBe(4)
        ->and(round($summary['cost_per_fill_usd'], 6))->toEqual(0.0006);

    expect($props['review']['unmatched'])->toBe(['province' => 0, 'district' => 0, 'commune' => 1])
        ->and($props['review']['rescued_by_shortlist'])->toBe(1)
        ->and($props['review']['reasons'][0])->toBe(['reason' => 'Not every level matched a Pancake location.', 'total' => 1]);

    // Last 7 days by default, one point per day; today's point holds the rows.
    expect($props['daily'])->toHaveCount(7)
        ->and(end($props['daily']))->toMatchArray(['date' => '2026-10-08', 'filled' => 2, 'needs_review' => 1, 'no_address' => 1, 'skipped' => 1]);
});

it('filters the rows by status, shop and date', function () {
    $other = Shop::factory()->forWorkspace($this->workspace)->create();

    autofillRow($this->shop, AddressAutofill::UPDATED);
    autofillRow($this->shop, AddressAutofill::NEEDS_REVIEW);
    autofillRow($other, AddressAutofill::NEEDS_REVIEW);
    autofillRow($this->shop, AddressAutofill::NEEDS_REVIEW, ['created_at' => now()->subDays(20)]);

    expect(statsPage($this->workspace, ['status' => 'needs_review'])['rows']['data'])->toHaveCount(2)
        ->and(statsPage($this->workspace, ['shop_id' => $this->shop->id])['summary']['total'])->toBe(2)
        ->and(statsPage($this->workspace, ['date_from' => '2026-09-01', 'date_to' => '2026-10-08'])['summary']['total'])->toBe(4);
});

it('only counts shops in this workspace', function () {
    ['workspace' => $elsewhere] = makeWorkspaceWithOwner();
    autofillRow(Shop::factory()->forWorkspace($elsewhere)->create(), AddressAutofill::UPDATED);
    autofillRow($this->shop, AddressAutofill::UPDATED);

    expect(statsPage($this->workspace)['summary']['total'])->toBe(1)
        // Another workspace's shop id in the filter is ignored, not trusted.
        ->and(statsPage($this->workspace, ['shop_id' => AddressAutofill::where('shop_id', '!=', $this->shop->id)->value('shop_id')])['summary']['total'])->toBe(1);
});
