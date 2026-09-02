<?php

namespace App\Http\Controllers\Workspaces\SalesMarketing;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\TestingItem;
use Modules\MetaAds\Services\TestingDailyRecordSync;
use Modules\MetaAds\Services\TestingItemResolver;

/**
 * New Creatives Tracker — the campaigns and ad sets a workspace has put under
 * test, and the daily numbers that accumulate against them.
 *
 * The picker feed is its own endpoint rather than page props: a workspace can
 * have a thousand-odd active campaigns and ad sets, which is far too much to
 * ship on every page load for a modal that may never be opened.
 */
class NewCreativesTrackerController extends Controller
{
    use AuthorizesRequests;

    /** Rows the picker returns per request — enough to scroll, small enough to be cheap. */
    private const PICKER_LIMIT = 100;

    public function __construct(
        private readonly TestingDailyRecordSync $sync,
        private readonly TestingItemResolver $resolver,
    ) {}

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->guard($workspace);

        $filters = $this->filters($request);

        $paginator = TestingItem::where('workspace_id', $workspace->id)
            ->with(['dailyRecords', 'product:id,title'])
            ->when($filters['account'], fn ($q, $id) => $q->where('meta_ads_account_id', $id))
            ->when($filters['type'], fn ($q, $type) => $q->where('item_type', $type))
            ->when($filters['source'], fn ($q, $source) => $q->where('source', $source))
            ->when(
                $filters['search'] || $filters['start_from'] || $filters['start_to'],
                fn ($q) => $this->constrainByEntity($q, $filters),
            )
            ->latest('id')
            ->paginate($filters['per_page'])
            // Carries the filters into the pagination links, so paging keeps
            // whatever narrowing is applied.
            ->withQueryString();

        // Map in place: the paginator keeps its own page/total metadata while
        // its rows become the shape the table renders.
        $paginator->setCollection($this->withNames($paginator->getCollection()));

        return Inertia::render('workspaces/sales-marketing/new-creatives-tracker/index', [
            'workspace' => $workspace,
            'items' => $paginator,
            // A test is a fixed 7 days, so the grid is always 7 columns wide —
            // a short test shows empty cells rather than a narrower table.
            'maxDay' => TestingDailyRecordSync::MAX_DAY,
            'accounts' => $this->accountOptions($workspace),
            'can' => [
                'create' => $request->user()->hasPermission(Permission::CreateNewCreativesTracker->value, $workspace),
                'edit' => $request->user()->hasPermission(Permission::EditNewCreativesTracker->value, $workspace),
                'delete' => $request->user()->hasPermission(Permission::DeleteNewCreativesTracker->value, $workspace),
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * The narrowing applied to the list, read straight off the query string so
     * a refresh — or a shared link — lands on the same view.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'account' => ['nullable', 'numeric'],
            'type' => ['nullable', 'in:campaign,ad_set'],
            'source' => ['nullable', 'in:meta,manual'],
            'start_from' => ['nullable', 'date'],
            'start_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        return [
            'search' => trim((string) ($validated['search'] ?? '')) ?: null,
            'account' => $validated['account'] ?? null,
            'type' => $validated['type'] ?? null,
            'source' => $validated['source'] ?? null,
            'start_from' => $validated['start_from'] ?? null,
            'start_to' => $validated['start_to'] ?? null,
            'per_page' => (int) ($validated['per_page'] ?? 25),
        ];
    }

    /**
     * Name and start-date live on the campaign / ad set, not on the tracked row,
     * and which table to look in depends on item_type. Rather than pulling every
     * id into PHP, this narrows each type with its own subquery and ORs them.
     *
     * @param  array<string, mixed>  $filters
     */
    private function constrainByEntity(Builder $query, array $filters): Builder
    {
        $matching = function (string $table) use ($filters) {
            return DB::table($table)
                ->select('id')
                ->when($filters['search'], fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
                ->when($filters['start_from'], fn ($q, $from) => $q->whereDate('start_time', '>=', $from))
                ->when($filters['start_to'], fn ($q, $to) => $q->whereDate('start_time', '<=', $to));
        };

        return $query->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('source', 'meta')
                ->where(fn ($q) => $q
                    ->where(fn ($q) => $q->where('item_type', 'campaign')
                        ->whereIn('item_id', $matching('meta_ads_campaigns')))
                    ->orWhere(fn ($q) => $q->where('item_type', 'ad_set')
                        ->whereIn('item_id', $matching('meta_ads_sets')))))
            // A manual row holds its own name and start date, so it is matched
            // on its own columns rather than through a Meta table.
            ->orWhere(fn ($q) => $q->where('source', 'manual')
                ->when($filters['search'], fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
                ->when($filters['start_from'], fn ($q, $from) => $q->whereDate('start_date', '>=', $from))
                ->when($filters['start_to'], fn ($q, $to) => $q->whereDate('start_date', '<=', $to))));
    }

    /**
     * Accounts to offer in the filter — only those that actually appear on a
     * tracked item, so the dropdown never lists one with nothing behind it.
     */
    private function accountOptions(Workspace $workspace): Collection
    {
        $ids = TestingItem::where('workspace_id', $workspace->id)
            ->whereNotNull('meta_ads_account_id')
            ->distinct()
            ->pluck('meta_ads_account_id');

        return AdAccount::whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($account) => [
                'id' => (string) $account->id,
                'name' => $account->name,
            ])
            ->values();
    }

    /**
     * Active campaigns / ad sets the viewer could add, filtered by an optional
     * search term and with anything already tracked left out.
     */
    public function availableItems(Request $request, Workspace $workspace): JsonResponse
    {
        $this->guard($workspace);

        $validated = $request->validate([
            'type' => ['required', 'in:campaign,ad_set'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $type = $validated['type'];
        $search = trim((string) ($validated['search'] ?? ''));

        $accountIds = $this->accountIds($workspace, $request->user());

        // Already tracked here, so the picker only ever offers something new.
        $taken = TestingItem::where('workspace_id', $workspace->id)
            ->where('item_type', $type)
            ->pluck('item_id');

        $query = ($type === 'campaign' ? Campaign::query() : AdSet::query())
            ->whereIn('meta_ads_account_id', $accountIds)
            ->where('effective_status', 'ACTIVE')
            ->whereNotIn('id', $taken)
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));

        $total = (clone $query)->count();

        $rows = $query->orderBy('name')
            ->limit(self::PICKER_LIMIT)
            ->get(['id', 'name', 'meta_ads_account_id']);

        $accountNames = AdAccount::whereIn('id', $rows->pluck('meta_ads_account_id')->unique())
            ->pluck('name', 'id');

        return response()->json([
            'items' => $rows->map(fn ($row) => [
                // String ids: Meta ids exceed what JS can hold exactly as a number.
                'id' => (string) $row->id,
                'name' => $row->name,
                'account_name' => $accountNames[$row->meta_ads_account_id] ?? null,
            ])->values(),
            'total' => $total,
            // The list is capped, so the UI can say "refine your search".
            'truncated' => $total > self::PICKER_LIMIT,
        ]);
    }

    /**
     * Add the picked campaigns / ad sets to the tracker. Ids are re-checked
     * against what the viewer can actually see — the modal's list is a
     * convenience, not the authority on access.
     */
    public function store(Request $request, Workspace $workspace)
    {
        $this->guard($workspace, Permission::CreateNewCreativesTracker);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'in:campaign,ad_set'],
            'items.*.item_id' => ['required', 'numeric'],
        ]);

        $accountIds = $this->accountIds($workspace, $request->user());
        $picked = collect($validated['items'])->unique(fn ($i) => $i['item_type'].':'.$i['item_id']);

        $rows = [];

        foreach (['campaign', 'ad_set'] as $type) {
            $ids = $picked->where('item_type', $type)->pluck('item_id');

            if ($ids->isEmpty()) {
                continue;
            }

            // Only ids that are really an active campaign/ad set on an account
            // this user can see survive — anything else is silently dropped.
            $allowed = ($type === 'campaign' ? Campaign::query() : AdSet::query())
                ->whereIn('meta_ads_account_id', $accountIds)
                ->where('effective_status', 'ACTIVE')
                ->whereIn('id', $ids)
                ->pluck('id');

            foreach ($allowed as $id) {
                $rows[] = [
                    'workspace_id' => $workspace->id,
                    'item_type' => $type,
                    'item_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (empty($rows)) {
            return redirect()->back()->with('error', 'Nothing was added — those items are no longer active or available.');
        }

        // Adding something already tracked is a no-op rather than an error: two
        // people picking the same ad set should not blow up the second save.
        DB::table('meta_ads_testing_items')->insertOrIgnore($rows);

        $added = TestingItem::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($rows) {
                foreach ($rows as $row) {
                    $q->orWhere(fn ($q) => $q
                        ->where('item_type', $row['item_type'])
                        ->where('item_id', $row['item_id']));
                }
            })
            ->get();

        // Stamp the ad account and product the item belongs to, then backfill
        // from the insights already on hand — so a new item arrives complete
        // rather than looking empty until the hourly sync next runs.
        $this->resolver->resolve($added);
        $this->sync->sync($added);

        $count = count($rows);

        return redirect()->back()->with('success', $count.' '.str('item')->plural($count).' added to the tracker.');
    }

    /**
     * Pause or resume a tracked item. A paused item keeps every day it has
     * already recorded and simply stops accruing new ones.
     */
    public function togglePause(Workspace $workspace, TestingItem $item)
    {
        $this->guard($workspace, Permission::EditNewCreativesTracker);

        // Route binding is workspace-blind, so confirm the item is this
        // workspace's before touching it.
        abort_unless($item->workspace_id === $workspace->id, 404);

        $item->update(['paused_at' => $item->paused_at ? null : now()]);

        if (! $item->paused_at) {
            // Resuming catches the item up on whatever it missed while paused.
            $this->sync->sync(collect([$item]));
        }

        return redirect()->back()->with(
            'success',
            $item->paused_at ? 'Tracking paused.' : 'Tracking resumed.',
        );
    }

    /**
     * Add a campaign or ad set by hand, for anything the Meta sync does not
     * carry. A manual row has no id to point at, so everything a synced row
     * would read back through that id is typed in instead.
     */
    public function storeManual(Request $request, Workspace $workspace)
    {
        $this->guard($workspace, Permission::CreateNewCreativesTracker);

        $validated = $request->validate([
            'item_type' => ['required', 'in:campaign,ad_set'],
            'name' => ['required', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'page_name' => ['nullable', 'string', 'max:255'],
            // Required: day 1 of the window is counted from here, and the day
            // cells are stored against the dates it produces.
            'start_date' => ['required', 'date'],
            'product_name' => ['nullable', 'string', 'max:255'],
        ]);

        TestingItem::create([
            'workspace_id' => $workspace->id,
            'source' => 'manual',
            'item_type' => $validated['item_type'],
            // No Meta id: the unique key tolerates any number of nulls, so
            // manual rows never collide with each other.
            'item_id' => null,
            'name' => $validated['name'],
            'account_name' => $validated['account_name'] ?? null,
            'page_name' => $validated['page_name'] ?? null,
            'start_date' => $validated['start_date'],
            'product_name' => $validated['product_name'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Manual item added.');
    }

    /**
     * Edit a manual item's details. Only manual ones: a synced item's name,
     * account and page are read back through its Meta id, so editing them here
     * would only be undone by the next look-up.
     */
    public function updateManual(Request $request, Workspace $workspace, TestingItem $item)
    {
        $this->guard($workspace, Permission::EditNewCreativesTracker);

        abort_unless($item->workspace_id === $workspace->id, 404);
        abort_unless($item->source === 'manual', 403, 'A synced item takes its details from Meta.');

        $validated = $request->validate([
            'item_type' => ['required', 'in:campaign,ad_set'],
            'name' => ['required', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'page_name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'product_name' => ['nullable', 'string', 'max:255'],
        ]);

        $moved = $item->start_date?->toDateString() !== $validated['start_date'];

        $item->update($validated);

        // The days are keyed by date and numbered from the start date, so
        // moving it would leave every recorded day pointing at the wrong one.
        // Re-stamp them against the new start rather than silently mismatching.
        if ($moved) {
            $this->restampDays($item);
        }

        return redirect()->back()->with('success', 'Item updated.');
    }

    /**
     * Remove an item from the tracker. Its daily records go with it — the
     * foreign key cascades — since they describe nothing on their own.
     */
    public function destroy(Workspace $workspace, TestingItem $item)
    {
        $this->guard($workspace, Permission::DeleteNewCreativesTracker);

        abort_unless($item->workspace_id === $workspace->id, 404);

        $item->delete();

        return redirect()->back()->with('success', 'Item removed from the tracker.');
    }

    /**
     * Re-date a manual item's recorded days after its start date moves, keeping
     * day 1 on the start date, day 2 the day after, and so on.
     */
    private function restampDays(TestingItem $item): void
    {
        foreach ($item->dailyRecords()->orderBy('day')->get() as $record) {
            if ($record->day === null) {
                continue;
            }

            $record->date = $item->start_date->copy()->addDays($record->day - 1);
            $record->save();
        }
    }

    /**
     * Type a day's figures for a manual item.
     *
     * Only manual items: a synced item's days are rebuilt from meta_ads_insights
     * on every run, so anything typed over them would be gone within the hour.
     *
     * The record is keyed by date, which is derived from the item's own start
     * date — day 1 is the start date, day 2 the day after. That keeps a manual
     * row on exactly the same footing as a synced one.
     */
    public function updateDay(Request $request, Workspace $workspace, TestingItem $item, int $day)
    {
        $this->guard($workspace, Permission::EditNewCreativesTracker);

        abort_unless($item->workspace_id === $workspace->id, 404);
        abort_unless($item->source === 'manual', 403, 'Synced items take their numbers from Meta.');
        abort_unless($day >= 1 && $day <= TestingDailyRecordSync::MAX_DAY, 404);
        abort_unless($item->start_date !== null, 422, 'This item needs a start date first.');

        $validated = $request->validate([
            'sales' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'ad_spent' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $date = $item->start_date->copy()->addDays($day - 1)->toDateString();

        $record = $item->dailyRecords()->firstOrNew(['date' => $date]);
        $record->day = $day;
        $record->fill($validated);

        // ROAS is never typed — it follows from the two figures that are, the
        // same definition the insights rollup uses.
        $adSpent = (float) $record->ad_spent;
        $record->roas = $adSpent > 0 ? round((float) $record->sales / $adSpent, 2) : null;

        $record->save();

        return redirect()->back();
    }

    /**
     * Record the calls made about a test — the intern's verdict and where the
     * money stands. Both are set by hand and neither is touched by the sync, so
     * this is the only thing that writes them.
     */
    public function updateDecision(Request $request, Workspace $workspace, TestingItem $item)
    {
        $this->guard($workspace, Permission::EditNewCreativesTracker);

        abort_unless($item->workspace_id === $workspace->id, 404);

        // `present` with `nullable`: sending the field explicitly clears it,
        // omitting it leaves the other column alone.
        $validated = $request->validate([
            'intern_decision' => ['sometimes', 'nullable', 'in:scale,split_50_50,killed'],
            'finance_status' => ['sometimes', 'nullable', 'in:for_collection,pending,collected'],
            // Manual only: a synced item takes its start date from the campaign
            // or ad set, and the day cells are anchored to it either way.
            'start_date' => ['sometimes', 'required', 'date'],
        ]);

        if (isset($validated['start_date']) && $item->source !== 'manual') {
            abort(403, 'A synced item takes its start date from Meta.');
        }

        $item->update($validated);

        return redirect()->back()->with('success', 'Updated.');
    }

    /**
     * Module flag plus a grant. Viewing, adding, editing and removing are four
     * separate grants, so each action names the one it needs rather than every
     * write riding on the view permission.
     */
    private function guard(Workspace $workspace, Permission $permission = Permission::ViewNewCreativesTracker): void
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize($permission->value, $workspace);
    }

    /**
     * Ad accounts this user can see in this workspace, the same way the Ads
     * Manager scopes its reads.
     */
    private function accountIds(Workspace $workspace, User $user): Collection
    {
        return AdAccount::forWorkspace($workspace)
            ->visibleTo($user, $workspace)
            ->pluck('meta_ads_accounts.id');
    }

    /**
     * Shape each tracked item for the page: its name, its numbers keyed by test
     * day, and the period totals. Names and start times are batched per type so
     * the list costs two queries rather than one per row.
     *
     * @param  Collection<int, TestingItem>  $items
     */
    private function withNames(Collection $items): Collection
    {
        $meta = [];

        foreach ($items->groupBy('item_type') as $type => $group) {
            $table = $type === 'campaign' ? 'meta_ads_campaigns' : 'meta_ads_sets';

            $meta[$type] = DB::table($table)
                ->whereIn('id', $group->pluck('item_id'))
                ->get(['id', 'name', 'start_time'])
                ->keyBy('id');
        }

        // One lookup for every account on the list, rather than per row.
        $accountNames = AdAccount::whereIn('id', $items->pluck('meta_ads_account_id')->filter()->unique())
            ->pluck('name', 'id');

        return $items->map(function (TestingItem $item) use ($meta, $accountNames) {
            // A synced row reads its name / start date back through its Meta
            // id; a manual one stores them on the row itself.
            $row = $item->item_id !== null
                ? ($meta[$item->item_type][$item->item_id] ?? null)
                : null;

            // Day-keyed so the table can look up a column directly. A record
            // whose item has no start_time has no day and is left out of the
            // grid, but still counts toward the totals below.
            $days = $item->dailyRecords
                ->filter(fn ($record) => $record->day !== null)
                ->keyBy('day')
                ->map(fn ($record) => [
                    'date' => $record->date?->toDateString(),
                    'sales' => (float) $record->sales,
                    'ad_spent' => (float) $record->ad_spent,
                    'roas' => $record->roas !== null ? (float) $record->roas : null,
                ]);

            $sales = (float) $item->dailyRecords->sum('sales');
            $adSpent = (float) $item->dailyRecords->sum('ad_spent');

            return [
                'id' => $item->id,
                'item_type' => $item->item_type,
                'item_id' => (string) $item->item_id,
                'source' => $item->source,
                'name' => $item->name ?? $row->name ?? null,
                'account_id' => $item->meta_ads_account_id ? (string) $item->meta_ads_account_id : null,
                // Manual rows keep the account as text; synced ones resolve it.
                'account_name' => $item->account_name
                    ?? ($accountNames[$item->meta_ads_account_id] ?? null),
                'page_name' => $item->page_name,
                'product' => $item->product_name ?? $item->product?->title,
                'is_paused' => $item->paused_at !== null,
                'intern_decision' => $item->intern_decision,
                'finance_status' => $item->finance_status,
                'start_date' => $item->start_date?->toDateString()
                    ?? ($row?->start_time ? Carbon::parse($row->start_time)->toDateString() : null),
                'days' => $days,
                'last_day' => (int) ($days->keys()->max() ?? 0),
                'total' => [
                    'sales' => round($sales, 2),
                    'ad_spent' => round($adSpent, 2),
                    // Blended over the whole test, not a mean of daily ROAS.
                    'roas' => $adSpent > 0 ? round($sales / $adSpent, 2) : null,
                ],
                'created_at' => $item->created_at?->toDateString(),
            ];
        })->values();
    }
}
