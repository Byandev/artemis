<?php

namespace Modules\Creatives\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Creatives\Http\Requests\StoreCreativeRequest;
use Modules\Creatives\Http\Requests\StoreReviewRequest;
use Modules\Creatives\Http\Requests\UpdateCreativeRequest;
use Modules\Creatives\Models\AdsCampaign;
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
                    'reviews' => fn ($q) => $q->with('reviewer:id,name')->oldest(),
                    'latestAdsCampaign',
                ])
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
                AllowedFilter::callback('review_status', function ($query, $value) {
                    $query->whereHas('latestReview', fn ($q) => $q->where('status', $value));
                }),
            ])
            ->allowedSorts([
                AllowedSort::field('name'),
                AllowedSort::field('creative_date'),
                AllowedSort::field('format'),
                AllowedSort::field('created_at'),
            ])
            ->defaultSort('-creative_date', '-id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn ($c) => $this->formatCreative($c));

        return Inertia::render('workspaces/creatives/index', [
            'workspace' => $workspace,
            'creatives' => $creatives,
            'query' => [
                ...$request->only(['sort', 'page']),
                'per_page' => $request->integer('per_page', 25),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(StoreCreativeRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateCreatives->value, $workspace);

        $data = $request->safe()->except('picture_file');

        if ($request->hasFile('picture_file')) {
            $path = $request->file('picture_file')->store("creatives/{$workspace->id}", 'public');
            $data['picture_url'] = Storage::url($path);
        }

        $creative = Creative::create([
            ...$data,
            'workspace_id' => $workspace->id,
            'creator_id' => $request->user()->id,
        ]);

        CreativeReview::create([
            'creative_id' => $creative->id,
            'reviewer_id' => $request->user()->id,
            'status' => 'waiting_for_submission',
        ]);

        return back();
    }

    public function update(UpdateCreativeRequest $request, Workspace $workspace, Creative $creative)
    {
        $this->guard($request, $workspace, $creative);
        $this->authorize(Permission::EditCreatives->value, $workspace);

        $data = $request->safe()->except('picture_file');

        if ($request->hasFile('picture_file')) {
            $this->deleteStoredFile($creative->picture_url);
            $path = $request->file('picture_file')->store("creatives/{$workspace->id}", 'public');
            $data['picture_url'] = Storage::url($path);
        }

        $creative->update($data);

        return back();
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
        $this->authorize(Permission::EditCreatives->value, $workspace);

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

        if ($review->creative_id !== $creative->id) {
            abort(404);
        }

        if ($review->reviewer_id !== $request->user()->id) {
            abort(403, 'You can only edit your own reviews.');
        }

        $review->update($request->validated());

        return back();
    }

    public function updateAdsCampaign(Request $request, Workspace $workspace, Creative $creative)
    {
        $this->guard($request, $workspace, $creative);
        $this->authorize(Permission::EditCreatives->value, $workspace);

        $validated = $request->validate([
            'ads_status' => ['required', Rule::in(['pending', 'running', 'kill', 'skill'])],
            'ads_manager_link' => ['nullable', 'string', 'max:2048'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $campaign = $creative->latestAdsCampaign;

        if ($campaign) {
            $campaign->update($validated);
        } else {
            AdsCampaign::create([
                'creative_id' => $creative->id,
                ...$validated,
            ]);
        }

        return back();
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

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
            'script' => $c->script,
            'picture_url' => $c->picture_url,
            'reference_link' => $c->reference_link,
            'caption' => $c->caption,
            'headline' => $c->headline,
            'notes' => $c->notes,
            'creator' => $c->creator ? ['id' => $c->creator->id, 'name' => $c->creator->name] : null,
            'reviews' => $reviews,
            'review_count' => count($reviews),
            'latest_review' => $latestReview ? [
                'status' => $latestReview->status,
                'feedback' => $latestReview->feedback,
            ] : null,
            'ads_campaign' => $c->latestAdsCampaign ? [
                'id' => $c->latestAdsCampaign->id,
                'ads_status' => $c->latestAdsCampaign->ads_status,
                'ads_manager_link' => $c->latestAdsCampaign->ads_manager_link,
                'remarks' => $c->latestAdsCampaign->remarks,
            ] : null,
        ];
    }
}
