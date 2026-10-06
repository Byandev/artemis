<?php

namespace Modules\Creatives\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Creatives\Http\Controllers\Concerns\GuardsCreatives;
use Modules\Creatives\Http\Presenters\CreativePresenter;
use Modules\Creatives\Http\Requests\StoreCreativeRequest;
use Modules\Creatives\Http\Requests\UpdateCreativeRequest;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Services\CreativeMediaStorage;
use Modules\Products\Models\Product;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The creatives list and the create/edit forms. Uploaded files live in
 * CreativeMediaController (and CreativeMediaStorage); reviews in
 * CreativeReviewController.
 */
class CreativesController extends Controller
{
    use AuthorizesRequests, GuardsCreatives;

    public function __construct(
        private CreativePresenter $presenter,
        private CreativeMediaStorage $storage,
    ) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewCreatives->value, $workspace);

        $creatives = QueryBuilder::for(
            Creative::where('workspace_id', $workspace->id)
                ->when(
                    TeamVisibility::shouldScope($request->user(), $workspace),
                    fn ($q) => $q->whereHas('product.pages', fn ($p) => $p->visibleTo($request->user(), $workspace)),
                )
                ->with(CreativePresenter::relations())
                ->withCount('reviews')
        )
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('code', 'like', "%{$value}%")
                            ->orWhere('name', 'like', "%{$value}%")
                            ->orWhere('headline', 'like', "%{$value}%")
                            ->orWhere('description', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('format'),
                AllowedFilter::exact('ads_status'),
                AllowedFilter::exact('final_status'),
                AllowedFilter::exact('creator_id'),
                AllowedFilter::exact('approved_by'),
                AllowedFilter::exact('product_id'),
                AllowedFilter::callback('review_status', function ($query, $value) {
                    $query->whereHas('latestReview', fn ($q) => $q->where('status', $value));
                }),
                // Three independent date-range filters, one per date column.
                // whereDate keeps the end day inclusive for the datetime columns
                // (created_at / approved_at).
                AllowedFilter::callback('creative_date_from', fn ($q, $v) => $q->whereDate('creative_date', '>=', $v)),
                AllowedFilter::callback('creative_date_to', fn ($q, $v) => $q->whereDate('creative_date', '<=', $v)),
                AllowedFilter::callback('created_at_from', fn ($q, $v) => $q->whereDate('created_at', '>=', $v)),
                AllowedFilter::callback('created_at_to', fn ($q, $v) => $q->whereDate('created_at', '<=', $v)),
                AllowedFilter::callback('approved_at_from', fn ($q, $v) => $q->whereDate('approved_at', '>=', $v)),
                AllowedFilter::callback('approved_at_to', fn ($q, $v) => $q->whereDate('approved_at', '<=', $v)),
            ])
            ->allowedSorts([
                AllowedSort::field('code'),
                AllowedSort::field('name'),
                AllowedSort::field('creative_date'),
                // format is a MySQL ENUM; cast to CHAR so it sorts alphabetically
                // instead of by enum definition order.
                AllowedSort::callback('format', function ($query, bool $descending) {
                    $query->orderByRaw('CAST(format AS CHAR) '.($descending ? 'desc' : 'asc'));
                }),
                AllowedSort::field('created_at'),
                // ads_status is a MySQL ENUM, which sorts by definition order by
                // default. Cast to CHAR so it sorts alphabetically instead.
                AllowedSort::callback('ads_status', function ($query, bool $descending) {
                    $query->orderByRaw('CAST(ads_status AS CHAR) '.($descending ? 'desc' : 'asc'));
                }),
                AllowedSort::field('final_status'),
                AllowedSort::field('approved_at'),
                AllowedSort::field('review_count', 'reviews_count'),
                AllowedSort::callback('creator', function ($query, bool $descending) {
                    $query->orderBy(
                        User::select('name')->whereColumn('users.id', 'creatives.creator_id'),
                        $descending ? 'desc' : 'asc'
                    );
                }),
            ])
            ->defaultSort('-creative_date', '-id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn ($c) => $this->presenter->present($c, $workspace));

        $creatorIds = Creative::where('workspace_id', $workspace->id)
            ->whereNotNull('creator_id')
            ->distinct()
            ->pluck('creator_id');

        $creators = User::whereIn('id', $creatorIds)->select('id', 'name')->orderBy('name')->get();

        $approverIds = Creative::where('workspace_id', $workspace->id)
            ->whereNotNull('approved_by')
            ->distinct()
            ->pluck('approved_by');

        $approvers = User::whereIn('id', $approverIds)->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('workspaces/creatives/index', [
            'workspace' => $workspace,
            'creatives' => $creatives,
            'creators' => $creators,
            'approvers' => $approvers,
            'products' => $this->products($workspace),
            'reviewers' => $this->reviewers($workspace),
            'query' => [
                ...$request->only(['sort', 'page']),
                'per_page' => $request->integer('per_page', 25),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function create(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateCreatives->value, $workspace);

        return Inertia::render('workspaces/creatives/create', [
            'workspace' => $workspace,
            'products' => $this->products($workspace),
            'reviewers' => $this->reviewers($workspace),
        ]);
    }

    public function store(StoreCreativeRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateCreatives->value, $workspace);

        $data = $request->validated();

        // assigned_reviewer_ids lives in a pivot table, not on the creatives row.
        $reviewerIds = $data['assigned_reviewer_ids'] ?? [];
        unset($data['assigned_reviewer_ids']);

        // The uploaded file goes to the media collection, not a column.
        unset($data['media_key'], $data['media_name'], $data['media_file']);

        // Setting a non-default ads / final status at creation is gated by the
        // status permission, mirroring updates. Untouched fields fall back to
        // the DB defaults (pending ads / for-approval).
        $changesStatus = (($data['ads_status'] ?? 'pending') !== 'pending')
            || (($data['final_status'] ?? 'for_approval') !== 'for_approval');

        if ($changesStatus) {
            $this->authorize(Permission::UpdateCreativeStatus->value, $workspace);
        }

        // Stamp approved_at / approved_by when a creative is created already approved.
        if (($data['final_status'] ?? null) === 'approved') {
            $data['approved_at'] = now();
            $data['approved_by'] = $request->user()->id;
        }

        $creative = Creative::create([
            ...$data,
            'workspace_id' => $workspace->id,
            'creator_id' => $request->user()->id,
        ]);

        $creative->assignedReviewers()->sync($reviewerIds);

        $this->storage->attachFromRequest($request, $creative);

        return redirect()
            ->route('workspaces.creatives.index', $workspace)
            ->with('success', 'Creative created successfully');
    }

    /**
     * Assign reviewers to several creatives at once. `mode` is either
     * 'add' (attach the selected reviewers, keeping existing ones) or
     * 'replace' (overwrite each creative's reviewer set — an empty list
     * clears all). Reviewers live in the `creative_reviewers` pivot.
     */
    public function bulkAssignReviewers(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditCreatives->value, $workspace);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'reviewer_ids' => ['present', 'array'],
            'reviewer_ids.*' => ['integer', 'exists:users,id'],
            'mode' => ['required', Rule::in(['add', 'replace'])],
        ]);

        $creatives = Creative::where('workspace_id', $workspace->id)
            ->whereIn('id', $validated['ids'])
            ->get();

        DB::transaction(function () use ($creatives, $validated) {
            foreach ($creatives as $creative) {
                if ($validated['mode'] === 'replace') {
                    $creative->assignedReviewers()->sync($validated['reviewer_ids']);
                } else {
                    $creative->assignedReviewers()->syncWithoutDetaching($validated['reviewer_ids']);
                }
            }
        });

        $count = $creatives->count();
        $verb = $validated['mode'] === 'replace' ? 'updated' : 'assigned';

        return back()->with('success', "Reviewers {$verb} for {$count} creative(s).");
    }

    public function edit(Request $request, Workspace $workspace, Creative $creative)
    {
        $this->guard($request, $workspace, $creative);
        $this->authorize(Permission::EditCreatives->value, $workspace);

        $creative->load(CreativePresenter::relations());

        return Inertia::render('workspaces/creatives/edit', [
            'workspace' => $workspace,
            'creative' => $this->presenter->present($creative, $workspace),
            'reviewers' => $this->reviewers($workspace),
            'products' => $this->products($workspace),
        ]);
    }

    public function update(UpdateCreativeRequest $request, Workspace $workspace, Creative $creative)
    {
        $this->guard($request, $workspace, $creative);

        $data = $request->validated();

        // assigned_reviewer_ids lives in a pivot table, not on the creatives row.
        $reviewerIds = null;
        if (array_key_exists('assigned_reviewer_ids', $data)) {
            $reviewerIds = $data['assigned_reviewer_ids'] ?? [];
            unset($data['assigned_reviewer_ids']);
        }

        // Status changes (final / ads) are gated by their own permission; any
        // other field edit requires the general edit permission. A single
        // request may touch either or both — e.g. inline status dropdowns post
        // only a status field, while the full edit form posts everything.
        $statusKeys = ['final_status', 'ads_status'];

        $changesStatus = collect($statusKeys)->contains(
            fn ($key) => array_key_exists($key, $data) && $data[$key] !== $creative->{$key}
        );

        $changesOther = collect($data)->keys()->diff($statusKeys)->isNotEmpty()
            || $reviewerIds !== null;

        if ($changesStatus) {
            $this->authorize(Permission::UpdateCreativeStatus->value, $workspace);
        }

        if ($changesOther) {
            $this->authorize(Permission::EditCreatives->value, $workspace);
        }

        // The uploaded file goes to the media collection, not a column.
        unset($data['media_key'], $data['media_name'], $data['media_file'], $data['remove_media']);

        // Stamp / clear approved_at + approved_by whenever the final status changes.
        if (array_key_exists('final_status', $data)) {
            $isApproved = $data['final_status'] === 'approved';
            $data['approved_at'] = $isApproved ? ($creative->approved_at ?? now()) : null;
            $data['approved_by'] = $isApproved ? ($creative->approved_by ?? $request->user()->id) : null;
        }

        $creative->update($data);

        if ($reviewerIds !== null) {
            $creative->assignedReviewers()->sync($reviewerIds);
        }

        $this->storage->attachFromRequest($request, $creative);

        // Inline edits (e.g. the status dropdowns on the index) post partial
        // payloads and expect to stay put — back() lands on the index they were
        // sent from, filters and all.
        if (! $request->has('name')) {
            return back()->with('success', 'Creative updated successfully');
        }

        // The full edit form posts every field and returns to the list. back()
        // would bounce to the edit page (it is the referer), so redirect to the
        // index explicitly, carrying the list's filters — the form forwards them
        // on the query string.
        return redirect()
            ->route('workspaces.creatives.index', [$workspace, ...$request->query()])
            ->with('success', 'Creative updated successfully');
    }

    public function destroy(Request $request, Workspace $workspace, Creative $creative)
    {
        $this->guard($request, $workspace, $creative);
        $this->authorize(Permission::DeleteCreatives->value, $workspace);

        $this->deleteStoredFile($creative->picture_url);
        $creative->delete();

        return back();
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Workspace members who are allowed to review creatives (have the
     * "Review Creatives" permission), plus the workspace owner.
     */
    private function reviewers(Workspace $workspace)
    {
        $userIds = DB::table('workspace_user')
            ->join('role_permissions', 'workspace_user.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('workspace_user.workspace_id', $workspace->id)
            ->where('permissions.name', Permission::ReviewCreatives->value)
            ->pluck('workspace_user.user_id')
            ->push($workspace->owner_id)
            ->filter()
            ->unique()
            ->all();

        return User::whereIn('id', $userIds)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    /**
     * Workspace products a creative can be linked to.
     */
    private function products(Workspace $workspace)
    {
        $user = request()->user();

        return Product::where('workspace_id', $workspace->id)
            ->when(
                TeamVisibility::shouldScope($user, $workspace),
                fn ($q) => $q->whereHas('pages', fn ($p) => $p->visibleTo($user, $workspace)),
            )
            ->select('id', 'title')
            ->orderBy('title')
            ->get();
    }

    private function deleteStoredFile(?string $url): void
    {
        if (! $url || ! str_starts_with($url, '/storage/')) {
            return;
        }

        $relativePath = substr($url, strlen('/storage/'));
        Storage::disk('public')->delete($relativePath);
    }
}
