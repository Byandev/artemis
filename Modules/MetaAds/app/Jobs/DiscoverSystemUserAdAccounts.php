<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\MetaAds\Jobs\Concerns\HandlesMetaSyncErrors;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\SyncRun;
use Modules\MetaAds\Services\MetaGraphClient;
use RuntimeException;
use Throwable;

/**
 * Walk every ad account visible to the BM system-user token and, for each one
 * that already exists locally (i.e. a workspace user has connected it via
 * OAuth), flip uses_system_user=true so future sync jobs route through the
 * shared token instead of the OAuth user token.
 *
 * Does NOT create new ad-account rows — workspace association still happens
 * via the existing OAuth flow (metaUsers ↔ workspaces).
 */
class DiscoverSystemUserAdAccounts implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public function handle(): void
    {
        $token = config('metaads.system_user_token');
        if (! $token) {
            throw new RuntimeException('META_ADS_SYSTEM_USER_TOKEN is not set.');
        }

        $run = SyncRun::start(
            entityType: SyncRun::ENTITY_AD_ACCOUNTS,
            scopeType: 'system_user',
        );

        try {
            $client = new MetaGraphClient($token);

            $flagged = 0;
            $skipped = 0;

            foreach ($client->paginated('me/adaccounts', ['fields' => 'id,account_id']) as $row) {
                $accountId = $row['account_id'] ?? preg_replace('/^act_/', '', (string) $row['id']);

                $updated = AdAccount::where('id', $accountId)
                    ->update(['uses_system_user' => true]);

                if ($updated) {
                    $flagged++;
                } else {
                    $skipped++;
                }
            }

            $run->succeed($flagged, [
                'flagged' => $flagged,
                'skipped_not_locally_known' => $skipped,
                'source' => 'system_user',
            ]);
        } catch (Throwable $e) {
            $this->handleSyncError($run, $e);
        }
    }
}
