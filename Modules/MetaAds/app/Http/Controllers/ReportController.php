<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\CustomBreakdown;
use Modules\MetaAds\Models\Report;

/**
 * SuperAds-style saved reports for the Ads Manager. A report stores a builder
 * configuration (accounts, date range, breakdown, metrics, sort, filters); the
 * builder page renders it against the existing AdsManagerController::data()
 * engine. Gated by the "View Meta Ads" permission at the route level.
 */
class ReportController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $reports = Report::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'description', 'kind', 'updated_at']);

        return Inertia::render('workspaces/integrations/meta-ads/reports/index', [
            'workspace' => $workspace,
            'reports' => $reports,
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', 'in:top_performers,custom_groups,categorization'],
            'config' => ['required', 'array'],
        ]);

        $report = Report::create([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'kind' => $validated['kind'],
            'config' => $validated['config'],
        ]);

        return redirect()->route('workspaces.metaads.reports.show', [$workspace, $report]);
    }

    public function show(Request $request, Workspace $workspace, Report $report): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);
        abort_unless($report->workspace_id === $workspace->id, 404);

        return Inertia::render('workspaces/integrations/meta-ads/reports/show', [
            'workspace' => $workspace,
            'report' => $report->only(['id', 'name', 'description', 'kind', 'config']),
            'accounts' => $this->accountsForWorkspace($request, $workspace),
            'customBreakdowns' => CustomBreakdown::where('workspace_id', $workspace->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($b) => ['id' => (string) $b->id, 'name' => $b->name])
                ->all(),
        ]);
    }

    public function update(Request $request, Workspace $workspace, Report $report): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);
        abort_unless($report->workspace_id === $workspace->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'config' => ['required', 'array'],
        ]);

        $report->update($validated);

        return back();
    }

    public function destroy(Request $request, Workspace $workspace, Report $report): RedirectResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);
        abort_unless($report->workspace_id === $workspace->id, 404);

        $report->delete();

        return redirect()->route('workspaces.metaads.reports.index', [$workspace]);
    }

    /**
     * The synced, visible ad accounts for the workspace — same payload the Ads
     * Manager shell builds, so the report builder's account selector matches.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function accountsForWorkspace(Request $request, Workspace $workspace): array
    {
        return AdAccount::forWorkspace($workspace)
            ->where('meta_ads_accounts.active_sync', true)
            ->visibleTo($request->user(), $workspace)
            ->select('meta_ads_accounts.id', 'meta_ads_accounts.name')
            ->orderBy('meta_ads_accounts.name')
            ->get()
            ->map(fn ($a) => ['id' => (string) $a->id, 'name' => $a->name])
            ->all();
    }
}
