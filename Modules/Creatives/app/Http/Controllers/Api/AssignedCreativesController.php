<?php

namespace Modules\Creatives\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Creatives\Models\Creative;

/**
 * Mobile app: the creatives waiting on the signed-in user's review — still
 * for approval, they are an assigned reviewer, and they have not left a
 * review yet — across every workspace they belong to that has the
 * Creatives module on.
 */
class AssignedCreativesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'format' => ['nullable', Rule::in(['all', 'image', 'video'])],
            'workspace' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $query = Creative::query()
            ->awaitingReviewBy($user)
            ->when($validated['workspace'] ?? null, fn ($q, $slug) => $q->whereHas('workspace', fn ($w) => $w->where('slug', $slug)))
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('headline', 'like', "%{$search}%");
                });
            })
            ->when(
                in_array($validated['format'] ?? 'all', ['image', 'video'], true),
                fn ($q) => $q->where('format', $validated['format']),
            );

        $total = (clone $query)->count();

        // Cursor pagination for the app's infinite scroll: reviewing a creative
        // drops it from this list, which would make offset pages skip rows.
        $creatives = $query
            ->with([
                'workspace:id,name,slug',
                'creator:id,name',
                'approvedBy:id,name',
                'product:id,title',
                'assignedReviewers:id,name',
                'reviews' => fn ($q) => $q->with('reviewer:id,name')->oldest(),
            ])
            ->orderByDesc('creative_date')
            ->orderByDesc('id')
            ->cursorPaginate($request->integer('per_page', 20))
            ->withQueryString()
            ->through(fn (Creative $c) => $this->formatCreative($c));

        return response()->json([...$creatives->toArray(), 'total' => $total]);
    }

    private function formatCreative(Creative $c): array
    {
        $reviews = $c->reviews->map(fn ($r) => [
            'id' => $r->id,
            'status' => $r->status,
            'feedback' => $r->feedback,
            'reviewer' => $r->reviewer ? ['id' => $r->reviewer->id, 'name' => $r->reviewer->name] : null,
            'created_at' => $r->created_at?->toIso8601String(),
        ])->values();

        $latestReview = $c->reviews->last();

        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'description' => $c->description,
            'format' => $c->format,
            'creative_date' => $c->creative_date?->format('Y-m-d'),
            'submission_status' => $c->submission_status,
            'script' => $c->script,
            // Stored as a relative /storage/... path for the web app; the
            // phone needs a full URL.
            'picture_url' => $c->picture_url && str_starts_with($c->picture_url, '/') ? url($c->picture_url) : $c->picture_url,
            'reference_link' => $c->reference_link,
            'caption' => $c->caption,
            'headline' => $c->headline,
            'notes' => $c->notes,
            'ads_status' => $c->ads_status,
            'final_status' => $c->final_status,
            'approved_at' => $c->approved_at?->toIso8601String(),
            'approved_by' => $c->approvedBy ? ['id' => $c->approvedBy->id, 'name' => $c->approvedBy->name] : null,
            'workspace' => ['id' => $c->workspace->id, 'name' => $c->workspace->name, 'slug' => $c->workspace->slug],
            'creator' => $c->creator ? ['id' => $c->creator->id, 'name' => $c->creator->name] : null,
            'product' => $c->product ? ['id' => $c->product->id, 'title' => $c->product->title] : null,
            'assigned_reviewers' => $c->assignedReviewers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values(),
            'reviews' => $reviews,
            'review_count' => $reviews->count(),
            'latest_review' => $latestReview ? ['status' => $latestReview->status, 'feedback' => $latestReview->feedback] : null,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
