<?php

use App\Models\PancakeUserDailyCallReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The CSR breakdown table at the foot of the analytics page.
 *
 * It lists the workspace's CSRs against the two nightly rollups. The roster is
 * every CSR attached to a shop, which is far longer than the handful who worked
 * any given week — so the table only carries the ones with a figure that moved
 * in the range, rather than padding itself with rows of zeros.
 */
const BRK_FROM = '2026-08-01';
const BRK_TO = '2026-08-05';

beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
    $this->shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
});

/** A CSR on this workspace's shops — on the roster, with nothing done yet. */
function brkCsr(Workspace $workspace, int $shopId, string $name): PancakeUser
{
    $csr = PancakeUser::create(['name' => $name]);

    // The pivot carries a uuid primary key of its own, hence the explicit insert.
    DB::table('pancake_shop_users')->insert([
        'id' => (string) Str::uuid(),
        'shop_id' => $shopId,
        'user_id' => $csr->id,
    ]);

    return $csr;
}

/** A day of the POS rollup. */
function brkPos(Workspace $workspace, int $shopId, PancakeUser $csr, array $figures, string $date = '2026-08-02'): void
{
    PancakeUserPosDailyReport::create([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => $shopId,
        'date' => $date,
        ...$figures,
    ]);
}

/** A day of the call rollup. */
function brkCall(Workspace $workspace, int $shopId, PancakeUser $csr, array $figures, string $date = '2026-08-02'): void
{
    PancakeUserDailyCallReport::create([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => $shopId,
        'date' => $date,
        ...$figures,
    ]);
}

/** The names the breakdown listed, in the order it listed them. */
function brkRows($owner, Workspace $workspace, array $query = []): array
{
    $params = http_build_query(['from' => BRK_FROM, 'to' => BRK_TO, ...$query]);

    $response = test()->actingAs($owner)->get(
        "/workspaces/{$workspace->slug}/csr/analytics?{$params}"
    );

    $response->assertOk();

    return collect($response->viewData('page')['props']['records']['data'])
        ->pluck('name')
        ->all();
}

test('a CSR who did nothing in the range is not a row', function () {
    $worked = brkCsr($this->workspace, $this->shop->id, 'Angeline Mercado');
    brkCsr($this->workspace, $this->shop->id, 'Cylde Dela Cruz');

    brkPos($this->workspace, $this->shop->id, $worked, ['total_orders' => 3, 'total_sales' => 6000]);

    // On the roster with no rollup row at all: every figure would coalesce to
    // zero, which is a row of nothing rather than a CSR to read.
    expect(brkRows($this->owner, $this->workspace))->toBe(['Angeline Mercado']);
});

test('a rollup row of zeros is still nothing done', function () {
    $worked = brkCsr($this->workspace, $this->shop->id, 'Angeline Mercado');
    $idle = brkCsr($this->workspace, $this->shop->id, 'Cylde Dela Cruz');

    brkPos($this->workspace, $this->shop->id, $worked, ['total_orders' => 3, 'total_sales' => 6000]);

    // The rollup wrote them a day, and every figure on it is zero. Having a row
    // is not the test — having a figure is.
    brkPos($this->workspace, $this->shop->id, $idle, ['total_orders' => 0, 'total_sales' => 0]);
    brkCall($this->workspace, $this->shop->id, $idle, ['total_called' => 0, 'total_call_time' => 0]);

    expect(brkRows($this->owner, $this->workspace))->toBe(['Angeline Mercado']);
});

test('one figure moving is enough to be listed', function () {
    $seller = brkCsr($this->workspace, $this->shop->id, 'Angeline Mercado');
    $caller = brkCsr($this->workspace, $this->shop->id, 'Mariel Bautista');
    $confirmer = brkCsr($this->workspace, $this->shop->id, 'Zinnia Cruz');

    brkPos($this->workspace, $this->shop->id, $seller, ['total_orders' => 3, 'total_sales' => 6000]);

    // No sales at all, but a call placed — the call rollup alone qualifies them.
    brkCall($this->workspace, $this->shop->id, $caller, [
        'total_rmo_called' => 4,
        'total_rmo_call_time' => 300,
    ]);

    // Deliveries confirmed and not one of them called: a real figure, so the
    // row stays even though the RMO % beside it reads 0.
    brkCall($this->workspace, $this->shop->id, $confirmer, [
        'total_rmo_confirmed_count' => 66,
        'total_rmo_assigned_count' => 0,
    ]);

    expect(brkRows($this->owner, $this->workspace))
        ->toEqualCanonicalizing(['Angeline Mercado', 'Mariel Bautista', 'Zinnia Cruz']);
});

test('a figure outside the range does not put a CSR in the table', function () {
    $worked = brkCsr($this->workspace, $this->shop->id, 'Angeline Mercado');
    $earlier = brkCsr($this->workspace, $this->shop->id, 'Cylde Dela Cruz');

    brkPos($this->workspace, $this->shop->id, $worked, ['total_orders' => 3, 'total_sales' => 6000]);
    // Busy the month before, nothing in the window being read.
    brkPos($this->workspace, $this->shop->id, $earlier, ['total_orders' => 9, 'total_sales' => 90000], '2026-07-02');

    expect(brkRows($this->owner, $this->workspace))->toBe(['Angeline Mercado']);
});

test('the pagination count is the CSRs listed, not the whole roster', function () {
    $worked = brkCsr($this->workspace, $this->shop->id, 'Angeline Mercado');
    brkPos($this->workspace, $this->shop->id, $worked, ['total_orders' => 3, 'total_sales' => 6000]);

    foreach (range(1, 40) as $i) {
        brkCsr($this->workspace, $this->shop->id, "Idle CSR {$i}");
    }

    $response = $this->actingAs($this->owner)->get(
        "/workspaces/{$this->workspace->slug}/csr/analytics?from=".BRK_FROM.'&to='.BRK_TO
    );

    // 41 on the roster, one of them with a figure — the footer has to say one,
    // or the reader pages through four screens of zeros looking for the rest.
    expect($response->viewData('page')['props']['records']['total'])->toBe(1);
});
