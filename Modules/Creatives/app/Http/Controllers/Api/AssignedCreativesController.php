<?php

namespace Modules\Creatives\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Creatives\Http\Presenters\TrackerCreativePresenter;
use Modules\Creatives\Models\Creative;

/**
 * Mobile app: the creatives the signed-in user reviews — still for approval
 * and they are an assigned reviewer — across every workspace they belong to
 * that has the Creatives module on. Ones they already reviewed stay listed
 * (`my_review` is set) so the app can tick them off.
 */
class AssignedCreativesController extends Controller
{
    public function __construct(private TrackerCreativePresenter $presenter) {}

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
            ->assignedForApprovalTo($user)
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
        $toReview = (clone $query)->whereDoesntHave('reviews', fn ($q) => $q->where('reviewer_id', $user->id))->count();

        // Cursor pagination for the app's infinite scroll: a creative leaving
        // "for approval" drops out of this list, which would make offset pages skip rows.
        $creatives = $query
            ->with(TrackerCreativePresenter::relations())
            ->orderByDesc('creative_date')
            ->orderByDesc('id')
            ->cursorPaginate($request->integer('per_page', 20))
            ->withQueryString()
            ->through(fn (Creative $c) => $this->presenter->present($c, $user));

        return response()->json([...$creatives->toArray(), 'total' => $total, 'to_review' => $toReview]);
    }
}
