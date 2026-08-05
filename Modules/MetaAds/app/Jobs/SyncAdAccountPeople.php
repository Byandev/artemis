<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\MetaAds\Exceptions\MetaGraphException;
use Modules\MetaAds\Jobs\Concerns\HandlesMetaSyncErrors;
use Modules\MetaAds\Jobs\Concerns\SerializesPerAdAccount;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdAccountPerson;
use Modules\MetaAds\Models\SyncRun;
use Throwable;

/**
 * Pull the people Meta says have access to an ad account — the Business
 * Manager "People" list — into `meta_ads_account_people`.
 *
 * Uses `GET /act_<id>/assigned_users`, which requires the account's owning
 * business id and the `business_management` scope (already requested at OAuth).
 */
class SyncAdAccountPeople implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels, SerializesPerAdAccount;

    public int $timeout = 300;

    public int $tries = 5;

    /**
     * Meta error codes that mean "this token may never read this account's
     * people" — a missing permission or a business the token can't see. Retrying
     * cannot fix those, so the run is recorded as failed without rethrowing and
     * poisoning the rest of the chain.
     */
    private const PERMANENT_ACCESS_CODES = [10, 100, 200, 272, 294];

    public function __construct(
        public AdAccount $adAccount,
        public ?int $syncRunId = null,
    ) {}

    public function handle(): void
    {
        $run = $this->syncRunId !== null
            ? SyncRun::findOrFail($this->syncRunId)
            : SyncRun::start(
                entityType: SyncRun::ENTITY_AD_ACCOUNT_PEOPLE,
                scopeType: AdAccount::class,
                scopeId: $this->adAccount->id,
            );
        $this->syncRunId = $run->id;

        // assigned_users is only addressable through the owning business. Accounts
        // with no business (personal ad accounts) have no People list to read.
        if (empty($this->adAccount->business_id)) {
            $run->succeed(0, ['skipped' => 'no_business_id']);

            return;
        }

        try {
            $client = $this->adAccount->graphClient();

            $seen = [];
            $count = 0;

            $rows = $client->paginated("{$this->adAccount->graphAccountId()}/assigned_users", [
                'business' => $this->adAccount->business_id,
                'fields' => 'id,name,user_type,tasks',
            ]);

            foreach ($rows as $row) {
                $personId = $row['id'] ?? null;

                if ($personId === null) {
                    continue;
                }

                $tasks = array_values(array_filter((array) ($row['tasks'] ?? [])));

                AdAccountPerson::updateOrCreate(
                    [
                        'meta_ads_account_id' => $this->adAccount->id,
                        'meta_user_id' => $personId,
                    ],
                    [
                        'name' => $row['name'] ?? null,
                        'user_type' => $row['user_type'] ?? null,
                        'tasks' => $tasks ?: null,
                        'role' => AdAccountPerson::roleFromTasks($tasks),
                        'last_synced_at' => Carbon::now(),
                    ],
                );

                $seen[] = (string) $personId;
                $count++;
            }

            // Drop anyone whose access was revoked on Meta's side since last run.
            $removed = AdAccountPerson::where('meta_ads_account_id', $this->adAccount->id)
                ->when($seen !== [], fn ($q) => $q->whereNotIn('meta_user_id', $seen))
                ->delete();

            $run->succeed($count, ['people_count' => $count, 'removed_count' => $removed]);
        } catch (MetaGraphException $e) {
            if (in_array((int) $e->errorCode, self::PERMANENT_ACCESS_CODES, true)) {
                Log::warning('Meta assigned_users not readable for ad account', [
                    'ad_account_id' => $this->adAccount->id,
                    'business_id' => $this->adAccount->business_id,
                    'error_code' => $e->errorCode,
                    'error_subcode' => $e->errorSubcode,
                    'message' => $e->getMessage(),
                ]);

                $run->fail($e, ['error_code' => $e->errorCode, 'permanent' => true]);

                return;
            }

            $this->handleSyncError($run, $e);
        } catch (Throwable $e) {
            $this->handleSyncError($run, $e);
        }
    }
}
