<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MetaAds\Models\CustomBreakdown;

/**
 * JSON CRUD for reusable custom breakdowns (named, rule-defined ad groups). The
 * report builder's breakdown panel reads/writes these; the aggregation in
 * AdsManagerController turns a selected breakdown into grouped buckets.
 */
class CustomBreakdownController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $breakdowns = CustomBreakdown::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'groups']);

        return response()->json(['breakdowns' => $breakdowns]);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $validated = $this->validatePayload($request);

        $breakdown = CustomBreakdown::create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'groups' => $validated['groups'],
        ]);

        return response()->json([
            'breakdown' => $breakdown->only(['id', 'name', 'groups']),
        ], 201);
    }

    public function update(
        Request $request,
        Workspace $workspace,
        CustomBreakdown $customBreakdown
    ): JsonResponse {
        abort_unless($request->user()->isMemberOf($workspace), 403);
        abort_unless($customBreakdown->workspace_id === $workspace->id, 404);

        $validated = $this->validatePayload($request);

        $customBreakdown->update([
            'name' => $validated['name'],
            'groups' => $validated['groups'],
        ]);

        return response()->json([
            'breakdown' => $customBreakdown->only(['id', 'name', 'groups']),
        ]);
    }

    public function destroy(
        Request $request,
        Workspace $workspace,
        CustomBreakdown $customBreakdown
    ): JsonResponse {
        abort_unless($request->user()->isMemberOf($workspace), 403);
        abort_unless($customBreakdown->workspace_id === $workspace->id, 404);

        $customBreakdown->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array{name: string, groups: array<int, array{name: string, match: string, rules: array}>}
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*.name' => ['required', 'string', 'max:255'],
            'groups.*.match' => ['required', 'in:all,any'],
            'groups.*.rules' => ['required', 'array', 'min:1'],
            'groups.*.rules.*.field' => ['required', 'in:name'],
            'groups.*.rules.*.op' => ['required', 'in:is,is_not,contains,not_contains'],
            'groups.*.rules.*.value' => ['required', 'string', 'max:255'],
        ]);
    }
}
