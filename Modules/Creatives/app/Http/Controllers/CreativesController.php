<?php

namespace Modules\Creatives\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Modules\Creatives\Http\Requests\StoreCreativeRequest;
use Modules\Creatives\Http\Requests\StoreReviewRequest;
use Modules\Creatives\Http\Requests\UpdateCreativeRequest;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CreativesController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewCreatives->value, $workspace);

        $creatives = QueryBuilder::for(
            Creative::where('workspace_id', $workspace->id)
                ->with([
                    'creator:id,name',
                    'approvedBy:id,name',
                    'product:id,title',
                    'assignedReviewers:id,name',
                    'reviews' => fn ($q) => $q->with('reviewer:id,name')->oldest(),
                ])
                ->withCount('reviews')
        )
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('headline', 'like', "%{$value}%")
                            ->orWhere('description', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('format'),
                AllowedFilter::exact('ads_status'),
                AllowedFilter::exact('creator_id'),
                AllowedFilter::exact('product_id'),
                AllowedFilter::callback('review_status', function ($query, $value) {
                    $query->whereHas('latestReview', fn ($q) => $q->where('status', $value));
                }),
                AllowedFilter::callback('date_from', function ($query, $value) {
                    $query->where('creative_date', '>=', $value);
                }),
                AllowedFilter::callback('date_to', function ($query, $value) {
                    $query->where('creative_date', '<=', $value);
                }),
            ])
            ->allowedSorts([
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
            ->through(fn ($c) => $this->formatCreative($c));

        $creatorIds = Creative::where('workspace_id', $workspace->id)
            ->whereNotNull('creator_id')
            ->distinct()
            ->pluck('creator_id');

        $creators = User::whereIn('id', $creatorIds)->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('workspaces/creatives/index', [
            'workspace' => $workspace,
            'creatives' => $creatives,
            'creators' => $creators,
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
        ]);
    }

    public function store(StoreCreativeRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateCreatives->value, $workspace);

        // New creatives always start as pending ads / for-approval (DB defaults).
        Creative::create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
            'creator_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('workspaces.creatives.index', $workspace)
            ->with('success', 'Creative created successfully');
    }

    public function edit(Request $request, Workspace $workspace, Creative $creative)
    {
        $this->guard($request, $workspace, $creative);
        $this->authorize(Permission::EditCreatives->value, $workspace);

        $creative->load(['creator:id,name', 'product:id,title', 'assignedReviewers:id,name', 'reviews' => fn ($q) => $q->with('reviewer:id,name')->oldest()]);

        return Inertia::render('workspaces/creatives/edit', [
            'workspace' => $workspace,
            'creative' => $this->formatCreative($creative),
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

        // Stamp / clear approved_at whenever the final status changes.
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

        // Inline edits (e.g. the status dropdowns on the index) post partial
        // payloads and expect to stay put; the full edit page posts the whole
        // form and should return to the list.
        if ($request->headers->get('X-Inertia-Partial-Component') || ! $request->has('name')) {
            return back()->with('success', 'Creative updated successfully');
        }

        return redirect()
            ->route('workspaces.creatives.index', $workspace)
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

    public function addReview(StoreReviewRequest $request, Workspace $workspace, Creative $creative)
    {
        $this->guard($request, $workspace, $creative);
        $this->authorize(Permission::ReviewCreatives->value, $workspace);

        CreativeReview::create([
            'creative_id' => $creative->id,
            'reviewer_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return back();
    }

    public function updateReview(StoreReviewRequest $request, Workspace $workspace, Creative $creative, CreativeReview $review)
    {
        $this->guard($request, $workspace, $creative);
        $this->authorize(Permission::ReviewCreatives->value, $workspace);

        if ($review->creative_id !== $creative->id) {
            abort(404);
        }

        if ($review->reviewer_id !== $request->user()->id) {
            abort(403, 'You can only edit your own reviews.');
        }

        $review->update($request->validated());

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
        return Product::where('workspace_id', $workspace->id)
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

    private function guard(Request $request, Workspace $workspace, ?Creative $creative = null): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403);
        }

        if ($creative && $creative->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    private function formatCreative(Creative $c): array
    {
        $reviews = $c->reviews->map(fn ($r) => [
            'id' => $r->id,
            'status' => $r->status,
            'feedback' => $r->feedback,
            'reviewer' => $r->reviewer ? ['id' => $r->reviewer->id, 'name' => $r->reviewer->name] : null,
            'created_at' => $r->created_at->format('M d, Y g:i A'),
        ])->values()->all();

        $latestReview = $c->reviews->last();

        return [
            'id' => $c->id,
            'name' => $c->name,
            'description' => $c->description,
            'format' => $c->format,
            'creative_date' => $c->creative_date?->format('Y-m-d'),
            'creative_date_label' => $c->creative_date_label,
            'submission_status' => $c->submission_status,
            'created_at' => $c->created_at?->format('M j, Y g:i A'),
            'script' => $c->script,
            'picture_url' => $c->picture_url,
            'reference_link' => $c->reference_link,
            'ads_status' => $c->ads_status,
            'ads_manager_link' => $c->ads_manager_link,
            'ads_remarks' => $c->ads_remarks,
            'final_status' => $c->final_status,
            'approved_at' => $c->approved_at?->format('M d, Y g:i A'),
            'approved_by' => $c->approvedBy ? ['id' => $c->approvedBy->id, 'name' => $c->approvedBy->name] : null,
            'caption' => $c->caption,
            'headline' => $c->headline,
            'notes' => $c->notes,
            'creator' => $c->creator ? ['id' => $c->creator->id, 'name' => $c->creator->name] : null,
            'product' => $c->product ? ['id' => $c->product->id, 'title' => $c->product->title] : null,
            'assigned_reviewers' => $c->assignedReviewers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values()->all(),
            'reviews' => $reviews,
            'review_count' => count($reviews),
            'latest_review' => $latestReview ? [
                'status' => $latestReview->status,
                'feedback' => $latestReview->feedback,
            ] : null,
        ];
    }
}
