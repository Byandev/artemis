<?php

namespace Modules\MetaAds\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\MonitorEvaluation;
use Modules\MetaAds\Models\MonitorStatusRule;
use Modules\MetaAds\Services\EntityMonitorService;

class EvaluateEntityMonitorCommand extends Command
{
    protected $signature = 'meta-ads:evaluate-entity-monitor';

    protected $description = 'Evaluate every campaign / ad set / ad within its 7-day test window and record today\'s suggested status + reason (the entity monitor daily history).';

    public function handle(EntityMonitorService $service): int
    {
        $today = Carbon::today()->toDateString();
        // Entities whose first spend day is within the last 7 days are still in
        // their test window.
        $from = Carbon::today()->subDays(EntityMonitorService::TEST_DAYS - 1)->toDateString();

        // Only workspaces that actually use the monitor (rules were ensured when
        // someone opened the board).
        $workspaceIds = MonitorStatusRule::query()->distinct()->pluck('workspace_id');

        if ($workspaceIds->isEmpty()) {
            $this->info('No workspaces have entity-monitor rules configured.');

            return self::SUCCESS;
        }

        $totalRows = 0;

        foreach ($workspaceIds as $workspaceId) {
            $workspace = Workspace::find($workspaceId);

            if (! $workspace) {
                continue;
            }

            $accountIds = AdAccount::forWorkspace($workspace)
                ->where('meta_ads_accounts.active_sync', true)
                ->pluck('meta_ads_accounts.id')
                ->map(fn ($id) => (string) $id)
                ->all();

            if (empty($accountIds)) {
                continue;
            }

            $rules = MonitorStatusRule::where('workspace_id', $workspace->id)->with('conditions')->get();

            foreach (['campaign', 'ad_set', 'ad'] as $level) {
                $rows = $service->buildRows($workspace, $level, $accountIds, $from, $today, $rules);

                foreach ($rows as $row) {
                    MonitorEvaluation::updateOrCreate(
                        [
                            'workspace_id' => $workspace->id,
                            'level' => $level,
                            'entity_id' => $row['entity_id'],
                            'date' => $today,
                        ],
                        [
                            'metrics' => $row['metrics'],
                            'suggested_status' => $row['suggested_status'],
                            'reason' => $row['reason'],
                            'evaluated_at' => now(),
                        ],
                    );

                    $totalRows++;
                }
            }
        }

        $this->info("Entity monitor: recorded {$totalRows} evaluation(s) for {$today}.");

        return self::SUCCESS;
    }
}
