<?php

namespace Modules\GencysERP\Support;

use App\Models\Workspace;
use Modules\GencysERP\Jobs\FetchPageDetailsJob;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Fans out the "fetch page detail" n8n trigger — one call per Gencys page — for a
 * workspace. Shared by the CLI trigger command and the pages sync callback (which
 * chains straight into detail fetching once the pages themselves land).
 */
class PageDetailsSync
{
    /**
     * Dispatch one page-details n8n trigger per page for the workspace. Returns
     * the page ids that were dispatched (empty when the workspace isn't
     * ERP-connected, has no matching pages, or no webhook is configured).
     *
     * @param  int[]  $pageIds  Limit to these Gencys page ids; empty = all pages.
     * @return int[]
     */
    public function dispatchForWorkspace(
        Workspace $workspace,
        array $pageIds = [],
        bool $sync = false,
        int $delay = 30,
        ?string $webhookOverride = null,
    ): array {
        $webhookUrl = $webhookOverride
            ?: config('services.n8n.gencys_page_details_webhook_url')
            ?: config('services.n8n.webhook_url');

        $apiKey = $workspace->apiKeys()->first();

        if (empty($webhookUrl) || ! $apiKey || blank($workspace->erp_username) || blank($workspace->erp_password)) {
            return [];
        }

        $pages = $workspace->gencysPages()
            ->whereNotNull('page_id')
            ->when(! empty($pageIds), fn ($q) => $q->whereIn('page_id', $pageIds))
            ->get();

        if ($pages->isEmpty()) {
            return [];
        }

        $callbackBase = rtrim(config('services.n8n.callback_base_url') ?: config('app.url'), '/');
        $callbackUrl = "{$callbackBase}/api/v1/public/gencys/page-details";

        $dispatched = [];

        foreach ($pages as $index => $page) {
            $run = GencysSyncRun::start(
                $workspace->id,
                null,
                GencysSyncRun::TYPE_PAGE_DETAILS,
                ['page_id' => $page->page_id],
            );

            $data = [
                'workspace_id' => $workspace->id,
                'api_key' => $apiKey->reveal(),
                'erp_username' => $workspace->erp_username,
                'erp_password' => $workspace->erp_password,
                'webhook_url' => $callbackUrl,
                'page_id' => $page->page_id,
                'sync_run_id' => $run->id,
            ];

            if ($sync) {
                FetchPageDetailsJob::dispatchSync($webhookUrl, $data, [$run->id]);
            } else {
                FetchPageDetailsJob::dispatch($webhookUrl, $data, [$run->id])
                    ->delay(now()->addSeconds($index * $delay));
            }

            $dispatched[] = $page->page_id;
        }

        return $dispatched;
    }
}
