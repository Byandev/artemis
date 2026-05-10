<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Jobs\Concerns\HandlesMetaSyncErrors;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\SyncRun;
use Modules\MetaAds\Models\User as MetaUser;
use Throwable;

class SyncMetaAdAccounts implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 8;

    public function __construct(public MetaUser $metaUser) {}

    public function handle(): void
    {
        $run = SyncRun::start(
            entityType: SyncRun::ENTITY_AD_ACCOUNTS,
            scopeType: MetaUser::class,
            scopeId: $this->metaUser->id,
        );

        try {
            $client = $this->metaUser->graphClient();

            $count = 0;
            $accountIds = [];

            $fields = 'id,account_id,name,currency,timezone_name,business_country_code,account_status,business';

            foreach ($client->paginated('me/adaccounts', ['fields' => $fields]) as $row) {
                // Meta returns id with `act_` prefix; account_id is the bare numeric form.
                $accountId = $row['account_id'] ?? preg_replace('/^act_/', '', (string) $row['id']);

                AdAccount::updateOrCreate(
                    ['id' => $accountId],
                    [
                        'name' => $row['name'] ?? $accountId,
                        'currency' => $row['currency'] ?? null,
                        'timezone_name' => $row['timezone_name'] ?? null,
                        'country_code' => $row['business_country_code'] ?? null,
                        'account_status' => isset($row['account_status']) ? (int) $row['account_status'] : null,
                        'business_id' => $row['business']['id'] ?? null,
                        'business_name' => $row['business']['name'] ?? null,
                        'last_synced_at' => Carbon::now(),
                    ],
                );

                $accountIds[$accountId] = ['permitted_tasks' => null];
                $count++;
            }

            $this->metaUser->adAccounts()->sync($accountIds);
            $this->metaUser->forceFill(['last_synced_at' => Carbon::now()])->save();

            $run->succeed($count, ['account_count' => $count]);
        } catch (Throwable $e) {
            $this->handleSyncError($run, $e);
        }
    }
}
