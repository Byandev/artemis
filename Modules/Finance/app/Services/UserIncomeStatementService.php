<?php

namespace Modules\Finance\Services;

use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionType;
use Modules\Finance\Models\UserIncomeStatement;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\Intern;
use Modules\GencysERP\Support\InternResolver;

/**
 * Builds, saves and reads the per-user slices of an income statement.
 *
 * Each delivered gencys order's `intern_brands_name` cell is resolved to an
 * intern (InternResolver) and then to the intern's linked user; revenue that
 * resolves to no user rolls into an "Unassigned" row. Per user we compute the
 * same two-tier P&L as the overall statement (Shipping + COD + VAT + the user's
 * flagged charged transactions = cost of sales; other charged txns = OPEX;
 * advisory = % of gross). COGS is deliberately not derived from order data — it
 * comes in as a charged transaction instead.
 *
 * The result is snapshotted into `finance_user_income_statements` when the parent
 * statement is saved/regenerated, so the pages read stored rows instead of
 * recomputing. A first view with no snapshot builds one lazily.
 */
class UserIncomeStatementService
{
    private const DELIVERED_STATUS = 'DELIVERED';

    private const SHIPPING_FEE_KEY = -1;

    private const COD_FEE_KEY = -2;

    private const VAT_KEY = -3;

    /** (Re)compute and store every per-user row. */
    public function snapshot(IncomeStatement $statement): void
    {
        $workspace = $statement->workspace;
        [$from, $to] = $this->range($statement);

        $agg = $this->aggregateByUser($workspace, $from, $to);

        $users = User::whereIn('id', array_keys($agg))->get()->keyBy('id');

        $rows = [];
        foreach (array_keys($agg) as $uid) {
            $user = $users->get($uid);
            if (! $user) {
                continue;
            }
            $payload = $this->buildUserStatement($workspace, $statement, $user, $from, $to);
            $rows[] = $this->rowFromPayload($uid, $user->name, $payload);
        }

        DB::transaction(function () use ($statement, $rows) {
            $statement->userStatements()->delete();
            foreach ($rows as $row) {
                $statement->userStatements()->create($row);
            }
        });
    }

    /**
     * The per-user P&L table payload (users[], total, overall, discrepancy) from
     * the stored snapshot, building it on first access. The discrepancy is the
     * overall workspace statement minus the attributed user rows — a non-zero
     * delivered/orders figure is revenue nobody is credited with (resolve it).
     */
    public function listPayload(IncomeStatement $statement): array
    {
        $this->ensureSnapshot($statement);

        $userRows = $statement->userStatements()
            ->whereNotNull('user_id')
            ->get()
            ->sortByDesc('net_profit')
            ->map(fn ($r) => $this->mapRow($r))
            ->values()->all();

        $total = $this->totalRow($userRows);
        $overall = $this->overallRow($statement);

        return [
            'users' => $userRows,
            'total' => $total,
            'overall' => $overall,
            'discrepancy' => $this->discrepancyRow($overall, $total),
        ];
    }

    /** The single-user `statement` payload for the overall statement page. */
    public function userPayload(IncomeStatement $statement, User $user): array
    {
        $this->ensureSnapshot($statement);

        $row = $statement->userStatements()->where('user_id', $user->id)->first();

        if (! $row) {
            return $this->zeroStatement($statement, $user->name);
        }

        return [
            'id' => null,
            'period_month' => $statement->period_month->toDateString(),
            'delivered' => (float) $row->delivered,
            'orders' => (int) $row->orders,
            'cod_fee_rate' => (float) $row->cod_fee_rate,
            'vat_rate' => (float) $row->vat_rate,
            'advisory_rate' => (float) $row->advisory_rate,
            'advisory_share' => (float) $row->advisory,
            'gencys_partner' => (bool) $row->gencys_partner,
            'gross_profit' => (float) $row->gross_profit,
            'total_expenses' => round((float) $row->cost_of_sales + (float) $row->opex, 2),
            'net_profit' => (float) $row->net_profit,
            'generated_at' => $row->updated_at?->toIso8601String(),
            'expenses' => collect($row->lines ?? [])->map(fn ($l) => [...$l, 'included' => true])->values()->all(),
            'products' => $this->userProductRows($statement, $user),
        ];
    }

    /**
     * The user's delivered revenue and cost of sales broken down by product,
     * where the product is resolved through the order's items: each
     * `gencys_order_items.sku` is a unit-code label matching an
     * `inventory_unit_codes.unit_code`, whose `product_id` is the product. Every
     * order maps to a single product (its unit codes all point to one); orders
     * whose items resolve to no product fall into a "Discrepancy" row (kept last).
     *
     * Cost of sales here is order-derived (COGS + shipping + COD + VAT) plus the
     * user's ad spend for the product. Other transactions still stay at the user
     * level.
     *
     * Ad spend comes from the user's Ad Spent transactions: each is split across
     * products (a product-share) and charged to users (a charge-to share), so the
     * user's ad spend for a product is that product's share apportioned by the
     * user's charge-to fraction of the whole transaction. It therefore sums back
     * to the user's ad spend on the P&L.
     *
     * @return list<array{product_id:?int, product:string, orders:int, delivered:float, cogs:float, shipping:float, cod_fee:float, vat:float, adspent:float, cost_of_sales:float, gross_profit:float, advisory:float, net_profit:float}>
     */
    private function userProductRows(IncomeStatement $statement, User $user): array
    {
        $workspace = $statement->workspace;
        [$from, $to] = $this->range($statement);

        $codRate = (float) $statement->cod_fee_rate;
        $vatRate = (float) $statement->vat_rate;
        $advisoryRate = (float) $statement->advisory_rate;
        $gencysPartner = (bool) $workspace->is_gencys_partner;

        $cells = $this->cellsForUser($workspace, $user->id, $from, $to);

        $rows = collect();
        $shipping = collect();

        if (! empty($cells)) {
            // Shipping per product (orders shipped out in the month).
            $shipped = GencysDailySalesOrder::where('workspace_id', $workspace->id)
                ->whereBetween('shipped_out_date', [$from->toDateString(), $to->toDateString()])
                ->whereIn('intern_brands_name', $cells)
                ->whereNotIn('platform', ['Shopee', 'TikTok'])
                ->whereNotLike('page', '%pikutin%')
                ->selectRaw('COALESCE(shipping_fee, 0) as shipping')
                ->selectSub($this->orderProductSubquery(), 'product_id');

            $shipping = DB::query()->fromSub($shipped, 't')
                ->selectRaw('product_id, COALESCE(SUM(shipping), 0) as shipping')
                ->groupBy('product_id')
                ->pluck('shipping', 'product_id');

            // Delivered revenue per product.
            $delivered = GencysDailySalesOrder::where('workspace_id', $workspace->id)
                ->where('parcel_status', self::DELIVERED_STATUS)
                ->whereBetween('parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->whereIn('intern_brands_name', $cells)
                ->whereNotIn('platform', ['Shopee', 'TikTok'])
                ->whereNotLike('page', '%pikutin%')
                ->selectRaw('COALESCE(price_final, 0) as revenue, COALESCE(total_cog, 0) as cogs')
                ->selectSub($this->orderProductSubquery(), 'product_id');

            $rows = DB::query()->fromSub($delivered, 't')
                ->selectRaw('product_id, COUNT(*) as orders, COALESCE(SUM(revenue), 0) as revenue, COALESCE(SUM(cogs), 0) as cogs')
                ->groupBy('product_id')
                ->get();
        }

        // The user's ad spend per product name → resolved to a product id.
        $adSpendByPid = $this->userAdSpendByProduct($workspace, $user->id, $from, $to);

        if ($rows->isEmpty() && empty($adSpendByPid)) {
            return [];
        }

        // Order economics keyed by product id ('' = the unresolved discrepancy).
        $orderByKey = [];
        foreach ($rows as $r) {
            $key = $r->product_id === null ? '' : (string) (int) $r->product_id;
            $orderByKey[$key] = ['orders' => (int) $r->orders, 'revenue' => round((float) $r->revenue, 2), 'cogs' => round((float) $r->cogs, 2)];
        }

        $keys = collect(array_keys($orderByKey))->merge(array_keys($adSpendByPid))->unique();

        $names = DB::table('products')
            ->whereIn('id', $keys->filter(fn ($k) => $k !== '')->map(fn ($k) => (int) $k)->all())
            ->pluck('name', 'id');

        $mapped = $keys->map(function ($key) use ($orderByKey, $adSpendByPid, $shipping, $names, $codRate, $vatRate, $advisoryRate, $gencysPartner) {
            $pid = $key === '' ? null : (int) $key;
            $order = $orderByKey[$key] ?? ['orders' => 0, 'revenue' => 0.0, 'cogs' => 0.0];
            $revenue = round((float) $order['revenue'], 2);
            $cogs = round((float) $order['cogs'], 2);
            $ship = round((float) $shipping->get($pid, 0), 2);
            $cod = round($revenue * $codRate, 2);
            $vat = round($cod * $vatRate, 2);
            $adspent = round((float) ($adSpendByPid[$key] ?? 0), 2);
            $costOfSales = round($cogs + $ship + $cod + $vat + $adspent, 2);
            $gross = round($revenue - $costOfSales, 2);

            // Advisory is a % of positive gross profit, gencys-partner only —
            // the same rule the statement and per-user rows use.
            $advisory = ($gencysPartner && $gross > 0) ? round($gross * $advisoryRate, 2) : 0.0;

            return [
                'product_id' => $pid,
                'product' => $pid !== null ? ($names[$pid] ?? 'Unknown') : 'Discrepancy',
                'orders' => (int) $order['orders'],
                'delivered' => $revenue,
                'cogs' => $cogs,
                'shipping' => $ship,
                'cod_fee' => $cod,
                'vat' => $vat,
                'adspent' => $adspent,
                'cost_of_sales' => $costOfSales,
                'gross_profit' => $gross,
                'advisory' => $advisory,
                'net_profit' => round($gross - $advisory, 2),
            ];
        });

        // Real products by delivered desc; the unresolved "Discrepancy" row (no
        // product) always sits last so it renders as the right-most column.
        $products = $mapped->filter(fn ($r) => $r['product_id'] !== null)
            ->sortByDesc('delivered')
            ->values();
        $discrepancy = $mapped->first(fn ($r) => $r['product_id'] === null);

        return $discrepancy ? $products->push($discrepancy)->all() : $products->all();
    }

    /**
     * The user's ad spend for the month broken down by product id, apportioned by
     * the user's charge-to fraction of each Ad Spent transaction. Keyed by product
     * id as a string; '' collects shares whose product name matches no product
     * (they surface in the per-product "Discrepancy" column).
     *
     * @return array<string, float>
     */
    private function userAdSpendByProduct(Workspace $workspace, int $userId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('finance_transaction_products as tp')
            ->join('finance_transactions as t', 't.id', '=', 'tp.transaction_id')
            ->join('finance_transaction_types as tt', 'tt.id', '=', 't.transaction_type_id')
            ->join('finance_transaction_charge_to as ct', 'ct.transaction_id', '=', 't.id')
            ->where('t.workspace_id', $workspace->id)
            ->where('t.type', 'out')
            ->where('ct.user_id', $userId)
            ->whereBetween('t.date', [$from->toDateString(), $to->toDateString()])
            ->where(function ($q) {
                $q->whereRaw('LOWER(tt.name) LIKE ?', ['%adspent%'])
                    ->orWhereRaw('LOWER(tt.name) LIKE ?', ['%ad spent%'])
                    ->orWhereRaw('LOWER(tt.name) LIKE ?', ['%ad spend%']);
            })
            ->groupBy('tp.product')
            // Product share × the user's fraction of the whole transaction.
            ->selectRaw('tp.product as name, SUM(tp.amount * ct.amount / NULLIF(t.amount, 0)) as adspent')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $idByName = DB::table('products')
            ->where('workspace_id', $workspace->id)
            ->whereIn('name', $rows->pluck('name')->all())
            ->pluck('id', 'name');

        $byPid = [];
        foreach ($rows as $r) {
            $key = isset($idByName[$r->name]) ? (string) $idByName[$r->name] : '';
            $byPid[$key] = ($byPid[$key] ?? 0) + (float) $r->adspent;
        }

        return $byPid;
    }

    /**
     * A correlated subquery resolving the outer `gencys_orders` row to a single
     * product id via its items: `gencys_order_items.sku` matches an
     * `inventory_unit_codes.unit_code` whose `product_id` is the product. MIN
     * picks one when an order carries several unit codes (all of one product).
     */
    private function orderProductSubquery(): Builder
    {
        return DB::table('inventory_unit_codes as uc')
            ->join('gencys_order_items as goi', 'goi.sku', '=', 'uc.unit_code')
            ->whereColumn('uc.workspace_id', 'gencys_orders.workspace_id')
            ->whereColumn('goi.order_id', 'gencys_orders.id')
            ->whereNotNull('uc.product_id')
            ->selectRaw('MIN(uc.product_id)');
    }

    /**
     * Unit codes referenced by this month's delivered order items that have no
     * row in `inventory_unit_codes` — the orders using them can't resolve to a
     * product, so they surface as the per-product "Discrepancy". Workspace-wide,
     * newest-first by how many orders use each, so the biggest gaps show first.
     *
     * @return list<array{unit_code:string, orders:int}>
     */
    public function missingUnitCodes(IncomeStatement $statement): array
    {
        $workspace = $statement->workspace;
        [$from, $to] = $this->range($statement);

        return DB::table('gencys_order_items as goi')
            ->join('gencys_orders as o', 'o.id', '=', 'goi.order_id')
            ->where('o.workspace_id', $workspace->id)
            ->where('o.parcel_status', self::DELIVERED_STATUS)
            ->whereNotIn('o.platform', ['Shopee', 'TikTok'])
            ->where('o.page', 'not like', '%pikutin%')
            ->whereBetween('o.parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereNotNull('goi.sku')
            ->where('goi.sku', '!=', '')
            ->whereNotExists(function ($q) use ($workspace) {
                $q->select(DB::raw('1'))
                    ->from('inventory_unit_codes as uc')
                    ->whereColumn('uc.unit_code', 'goi.sku')
                    ->where('uc.workspace_id', $workspace->id);
            })
            ->groupBy('goi.sku')
            ->orderByRaw('COUNT(DISTINCT goi.order_id) DESC')
            ->get(['goi.sku as unit_code', DB::raw('COUNT(DISTINCT goi.order_id) as orders')])
            ->map(fn ($r) => ['unit_code' => (string) $r->unit_code, 'orders' => (int) $r->orders])
            ->all();
    }

    private function ensureSnapshot(IncomeStatement $statement): void
    {
        if (! $statement->userStatements()->exists()) {
            $this->snapshot($statement);
        }
    }

    /**
     * Per-user aggregates keyed by user id. Orders whose `intern_brands_name`
     * resolves to no user are skipped here — they surface as the discrepancy
     * between the summed user rows and the overall statement.
     *
     * @return array<int, array{revenue:float, orders:int, shipping:float}>
     */
    private function aggregateByUser(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        $cellToUser = $this->cellUserResolver($workspace);
        $blank = ['revenue' => 0.0, 'orders' => 0, 'shipping' => 0.0];

        $agg = [];

        $revRows = GencysDailySalesOrder::where('workspace_id', $workspace->id)
            ->where('parcel_status', self::DELIVERED_STATUS)
            ->whereNotIn('platform', ['Shopee', 'TikTok'])
            ->whereNotLike('page', '%pikutin%')
            ->whereBetween('parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('intern_brands_name as cell, COUNT(*) as orders, COALESCE(SUM(price_final), 0) as revenue')
            ->groupBy('cell')
            ->get();

        foreach ($revRows as $r) {
            $uid = $cellToUser($r->cell);
            if ($uid === null) {
                continue;
            }
            $agg[$uid] ??= $blank;
            $agg[$uid]['revenue'] += (float) $r->revenue;
            $agg[$uid]['orders'] += (int) $r->orders;
        }

        $shipRows = GencysDailySalesOrder::where('workspace_id', $workspace->id)
            ->whereBetween('shipped_out_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('platform', ['Shopee', 'TikTok'])
            ->whereNotLike('page', '%pikutin%')
            ->selectRaw('intern_brands_name as cell, COALESCE(SUM(shipping_fee), 0) as shipping')
            ->groupBy('cell')
            ->get();

        foreach ($shipRows as $r) {
            $uid = $cellToUser($r->cell);
            if ($uid === null) {
                continue;
            }
            $agg[$uid] ??= $blank;
            $agg[$uid]['shipping'] += (float) $r->shipping;
        }

        return $agg;
    }

    /** The user's two-tier P&L for the month, with expense line items. */
    private function buildUserStatement(Workspace $workspace, IncomeStatement $incomeStatement, User $user, Carbon $from, Carbon $to): array
    {
        $codRate = (float) $incomeStatement->cod_fee_rate;
        $vatRate = (float) $incomeStatement->vat_rate;
        $advisoryRate = (float) $incomeStatement->advisory_rate;

        $cells = $this->cellsForUser($workspace, $user->id, $from, $to);

        $revenue = 0.0;
        $orders = 0;
        $shipping = 0.0;

        if (! empty($cells)) {
            $row = GencysDailySalesOrder::where('workspace_id', $workspace->id)
                ->where('parcel_status', self::DELIVERED_STATUS)
                ->whereBetween('parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->whereIn('intern_brands_name', $cells)
                ->whereNotIn('platform', ['Shopee', 'TikTok'])
                ->whereNotLike('page', '%pikutin%')
                ->selectRaw('COALESCE(SUM(price_final), 0) as revenue, COUNT(*) as orders')
                ->first();

            $revenue = (float) $row->revenue;
            $orders = (int) $row->orders;

            $shipping = (float) GencysDailySalesOrder::where('workspace_id', $workspace->id)
                ->whereBetween('shipped_out_date', [$from->toDateString(), $to->toDateString()])
                ->whereNotIn('platform', ['Shopee', 'TikTok'])
                ->whereNotLike('page', '%pikutin%')
                ->whereIn('intern_brands_name', $cells)
                ->sum('shipping_fee');
        }

        $cod = round($revenue * $codRate, 2);
        $vat = round($cod * $vatRate, 2);

        [$flagged, $opexBuckets] = $this->userTransactionBuckets($workspace, $user->id, $from, $to);
        $flaggedTotal = (float) collect($flagged)->sum('amount');
        $opexTotal = (float) collect($opexBuckets)->sum('amount');

        $lines = collect();
        if ($shipping > 0) {
            $lines->push($this->line(self::SHIPPING_FEE_KEY, 'Shipping Fee', round($shipping, 2), 'shipping_fee', 'cost_of_sales'));
        }
        if ($revenue > 0) {
            $lines->push($this->line(self::COD_FEE_KEY, 'COD Fee', $cod, 'cod_fee', 'cost_of_sales'));
        }
        if ($cod > 0) {
            $lines->push($this->line(self::VAT_KEY, 'VAT', $vat, 'vat', 'cost_of_sales'));
        }
        foreach ($flagged as $b) {
            $lines->push($this->line($b['type_key'], $b['type_name'], $b['amount'], 'transaction_type', 'cost_of_sales'));
        }
        foreach ($opexBuckets as $b) {
            $lines->push($this->line($b['type_key'], $b['type_name'], $b['amount'], 'transaction_type', 'opex'));
        }

        $p = $this->derivePnl(round($revenue, 2), $shipping, $cod, $vat, $flaggedTotal, $opexTotal, $advisoryRate, (bool) $workspace->is_gencys_partner);

        return [
            'delivered' => round($revenue, 2),
            'orders' => $orders,
            'cod_fee_rate' => $codRate,
            'vat_rate' => $vatRate,
            'advisory_rate' => $advisoryRate,
            'advisory_share' => $p['advisory'],
            'gencys_partner' => (bool) $workspace->is_gencys_partner,
            'gross_profit' => $p['gross'],
            'net_profit' => $p['net'],
            'expenses' => $lines->all(),
        ];
    }

    /** Two-tier P&L math. */
    private function derivePnl(float $revenue, float $shipping, float $cod, float $vat, float $flagged, float $opex, float $advisoryRate, bool $gencysPartner): array
    {
        $costOfSales = round($shipping + $cod + $vat + $flagged, 2);
        $gross = round($revenue - $costOfSales, 2);
        $advisory = ($gencysPartner && $gross > 0) ? round($gross * $advisoryRate, 2) : 0.0;
        $net = round($gross - $opex - $advisory, 2);

        return ['cost_of_sales' => $costOfSales, 'gross' => $gross, 'advisory' => $advisory, 'net' => $net];
    }

    /** Persist-ready row from a computed single-user payload. */
    private function rowFromPayload(?int $userId, string $name, array $p): array
    {
        $costOfSales = round($p['delivered'] - $p['gross_profit'], 2);
        $opex = round($p['gross_profit'] - $p['net_profit'] - $p['advisory_share'], 2);

        return [
            'user_id' => $userId,
            'user_name' => $name,
            'orders' => $p['orders'],
            'delivered' => $p['delivered'],
            'cost_of_sales' => $costOfSales,
            'gross_profit' => $p['gross_profit'],
            'advisory' => $p['advisory_share'],
            'opex' => $opex,
            'net_profit' => $p['net_profit'],
            'cod_fee_rate' => $p['cod_fee_rate'],
            'vat_rate' => $p['vat_rate'],
            'advisory_rate' => $p['advisory_rate'],
            'gencys_partner' => $p['gencys_partner'],
            'lines' => $p['expenses'],
        ];
    }

    /** The overall workspace statement as a P&L row, to diff the user rows against. */
    private function overallRow(IncomeStatement $statement): array
    {
        $delivered = round((float) $statement->total_delivered, 2);
        $gross = round((float) $statement->gross_profit, 2);
        $net = round((float) $statement->net_profit, 2);
        $advisory = round((float) $statement->advisory_share, 2);

        return [
            'user_id' => null,
            'name' => 'Overall',
            'orders' => (int) $statement->delivered_orders,
            'delivered' => $delivered,
            'cost_of_sales' => round($delivered - $gross, 2),
            'gross_profit' => $gross,
            'advisory' => $advisory,
            'opex' => round($gross - $net - $advisory, 2),
            'net_profit' => $net,
        ];
    }

    /**
     * The overall statement minus the summed user rows. Delivered and orders
     * should net to ~0 once every intern resolves to a user — a non-zero figure
     * is revenue nobody is credited with, i.e. something to resolve. (Cost of
     * sales, gross and net can still diverge: only transactions charged to a user
     * land on a user row, while the workspace statement counts them all.)
     */
    private function discrepancyRow(array $overall, array $total): array
    {
        $diff = fn (string $k) => round((float) $overall[$k] - (float) $total[$k], 2);

        return [
            'user_id' => null,
            'name' => 'Discrepancy',
            'orders' => (int) $overall['orders'] - (int) $total['orders'],
            'delivered' => $diff('delivered'),
            'cost_of_sales' => $diff('cost_of_sales'),
            'gross_profit' => $diff('gross_profit'),
            'advisory' => $diff('advisory'),
            'opex' => $diff('opex'),
            'net_profit' => $diff('net_profit'),
        ];
    }

    /** A memoized cell → user_id resolver for the workspace (null = unassigned). */
    private function cellUserResolver(Workspace $workspace): callable
    {
        $resolver = new InternResolver($workspace->id);
        $interns = Intern::where('workspace_id', $workspace->id)->get(['id', 'user_id'])->keyBy('id');
        $cache = [];

        return function (?string $cell) use ($resolver, $interns, &$cache): ?int {
            $key = (string) $cell;
            if (! array_key_exists($key, $cache)) {
                $internId = $resolver->resolve($cell);
                $cache[$key] = $internId ? ($interns[$internId]->user_id ?? null) : null;
            }

            return $cache[$key];
        };
    }

    /** Distinct delivered `intern_brands_name` cells that resolve to this user. */
    private function cellsForUser(Workspace $workspace, int $userId, Carbon $from, Carbon $to): array
    {
        $resolve = $this->cellUserResolver($workspace);

        return GencysDailySalesOrder::where('workspace_id', $workspace->id)
            ->where('parcel_status', self::DELIVERED_STATUS)
            ->whereNotIn('platform', ['Shopee', 'TikTok'])
            ->whereNotLike('page', '%pikutin%')
            ->whereBetween('parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereNotNull('intern_brands_name')
            ->where('intern_brands_name', '!=', '')
            ->distinct()
            ->pluck('intern_brands_name')
            ->filter(fn ($cell) => $resolve($cell) === $userId)
            ->values()
            ->all();
    }

    /**
     * The user's charged outflow transactions grouped by type, split into cost of
     * sales and OPEX by each type's `income_statement_section`. Types with a null
     * section (excluded) are dropped; a deleted/unknown type falls back to OPEX.
     *
     * @return array{0:list<array{type_key:int,type_name:string,amount:float}>, 1:list<array{type_key:int,type_name:string,amount:float}>}
     */
    private function userTransactionBuckets(Workspace $workspace, int $userId, Carbon $from, Carbon $to): array
    {
        $types = TransactionType::where('workspace_id', $workspace->id)
            ->get(['id', 'name', 'income_statement_section'])
            ->keyBy('id');

        // A transaction split across users contributes only this user's share.
        $rows = Transaction::query()
            ->join('finance_transaction_charge_to as ct', 'ct.transaction_id', '=', 'finance_transactions.id')
            ->where('finance_transactions.workspace_id', $workspace->id)
            ->where('finance_transactions.type', 'out')
            ->where('ct.user_id', $userId)
            ->whereBetween('finance_transactions.date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(finance_transactions.transaction_type_id, 0) as type_key, SUM(ct.amount) as total')
            ->groupBy('type_key')
            ->orderByDesc('total')
            ->get()
            ->map(function ($r) use ($types) {
                $key = (int) $r->type_key;
                $type = $key ? $types->get($key) : null;

                return [
                    'type_key' => $key,
                    'type_name' => $key ? ($type->name ?? 'Unknown') : 'Uncategorized',
                    'amount' => (float) $r->total,
                    'section' => $type ? $type->income_statement_section : 'opex',
                ];
            })
            ->reject(fn ($b) => $b['section'] === null);

        return [
            $rows->where('section', 'cost_of_sales')->map(fn ($b) => ['type_key' => $b['type_key'], 'type_name' => $b['type_name'], 'amount' => $b['amount']])->values()->all(),
            $rows->where('section', 'opex')->map(fn ($b) => ['type_key' => $b['type_key'], 'type_name' => $b['type_name'], 'amount' => $b['amount']])->values()->all(),
        ];
    }

    /** @return array{type_key:int, type_name:string, amount:float, source:string, section:string} */
    private function line(int $key, string $name, float $amount, string $source, string $section): array
    {
        return ['type_key' => $key, 'type_name' => $name, 'amount' => $amount, 'source' => $source, 'section' => $section];
    }

    /** A stored row → the list table's row shape. */
    private function mapRow(UserIncomeStatement $r): array
    {
        return [
            'user_id' => $r->user_id,
            'name' => $r->user_name,
            'orders' => (int) $r->orders,
            'delivered' => (float) $r->delivered,
            'cost_of_sales' => (float) $r->cost_of_sales,
            'gross_profit' => (float) $r->gross_profit,
            'advisory' => (float) $r->advisory,
            'opex' => (float) $r->opex,
            'net_profit' => (float) $r->net_profit,
        ];
    }

    /** Column-wise sum of the given rows for the table's Total row. */
    private function totalRow(array $rows): array
    {
        $sum = fn (string $k) => round(collect($rows)->sum($k), 2);

        return [
            'user_id' => null,
            'name' => 'Total',
            'orders' => (int) collect($rows)->sum('orders'),
            'delivered' => $sum('delivered'),
            'cost_of_sales' => $sum('cost_of_sales'),
            'gross_profit' => $sum('gross_profit'),
            'advisory' => $sum('advisory'),
            'opex' => $sum('opex'),
            'net_profit' => $sum('net_profit'),
        ];
    }

    /** A zero single-user statement payload (user with no snapshot row). */
    private function zeroStatement(IncomeStatement $statement, string $name): array
    {
        return [
            'id' => null,
            'period_month' => $statement->period_month->toDateString(),
            'delivered' => 0.0,
            'orders' => 0,
            'cod_fee_rate' => (float) $statement->cod_fee_rate,
            'vat_rate' => (float) $statement->vat_rate,
            'advisory_rate' => (float) $statement->advisory_rate,
            'advisory_share' => 0.0,
            'gencys_partner' => (bool) $statement->workspace->is_gencys_partner,
            'gross_profit' => 0.0,
            'total_expenses' => 0.0,
            'net_profit' => 0.0,
            'generated_at' => null,
            'expenses' => [],
            'products' => [],
        ];
    }

    /** @return array{0:Carbon, 1:Carbon} [from, to] for the statement's month. */
    private function range(IncomeStatement $statement): array
    {
        $start = $statement->period_month->copy()->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }
}
