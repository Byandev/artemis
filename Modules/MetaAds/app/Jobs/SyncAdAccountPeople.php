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
use Modules\MetaAds\Services\MetaGraphClient;
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

        try {
            $client = $this->adAccount->graphClient();

            $businessIds = $this->resolveBusinessIds($client);

            // assigned_users is only addressable through a business. An account
            // that is neither owned by nor shared into one (a personal ad
            // account) has no People list Meta will hand back.
            if ($businessIds === []) {
                $run->succeed(0, ['skipped' => 'no_business']);

                return;
            }

            $seen = [];
            $count = 0;
            $skippedUnnamed = 0;

            foreach ($businessIds as $businessId) {
                $rows = $client->paginated("{$this->adAccount->graphAccountId()}/assigned_users", [
                    'business' => $businessId,
                    'fields' => 'id,name,user_type,tasks',
                ]);

                foreach ($rows as $row) {
                    $personId = $row['id'] ?? null;

                    if ($personId === null) {
                        continue;
                    }

                    $name = $this->cleanName($row['name'] ?? null);

                    // No usable name means Meta wouldn't hand us the profile —
                    // restricted, deactivated, or deleted. Leave them out.
                    if ($name === null && config('metaads.people.skip_unnamed', true)) {
                        $skippedUnnamed++;

                        continue;
                    }

                    $tasks = array_values(array_filter((array) ($row['tasks'] ?? [])));

                    AdAccountPerson::updateOrCreate(
                        [
                            'meta_ads_account_id' => $this->adAccount->id,
                            'meta_user_id' => $personId,
                        ],
                        [
                            'name' => $name,
                            'user_type' => $row['user_type'] ?? null,
                            'tasks' => $tasks ?: null,
                            'role' => AdAccountPerson::roleFromTasks($tasks),
                            'source_business_id' => $businessId,
                            'last_synced_at' => Carbon::now(),
                        ],
                    );

                    // A person shared through two businesses is one row, counted once.
                    if (! in_array((string) $personId, $seen, true)) {
                        $seen[] = (string) $personId;
                        $count++;
                    }
                }
            }

            // Drop anyone whose access was revoked on Meta's side since last run.
            $removed = AdAccountPerson::where('meta_ads_account_id', $this->adAccount->id)
                ->when($seen !== [], fn ($q) => $q->whereNotIn('meta_user_id', $seen))
                ->delete();

            $run->succeed($count, [
                'people_count' => $count,
                'removed_count' => $removed,
                'businesses_queried' => count($businessIds),
                // Surfaced so a filter that's eating real people is visible on
                // the Sync Health page rather than silently shrinking the list.
                'skipped_unnamed' => $skippedUnnamed,
            ]);
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

    /**
     * Normalise an assigned user's name, returning null when Meta gave us
     * nothing real — an absent/blank name, or one of the generic placeholders
     * it substitutes for a profile it won't disclose (restricted, deactivated,
     * or deleted accounts).
     */
    private function cleanName(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $name = trim($raw);

        if ($name === '') {
            return null;
        }

        $placeholders = (array) config('metaads.people.placeholder_names', []);

        if (in_array(mb_strtolower($name), array_map('mb_strtolower', $placeholders), true)) {
            return null;
        }

        return $name;
    }

    /**
     * Businesses through which this account's people can be read.
     *
     * Owned accounts resolve in one call via the stored business_id. Accounts
     * with no owning business are not necessarily personal — they are often
     * *shared into* a portfolio, and `/act_<id>/agencies` lists exactly those
     * businesses. Only accounts that are neither owned nor shared come back
     * empty, and for those Meta genuinely exposes no People list.
     *
     * The agencies call is skipped for owned accounts to keep the daily job at
     * one Graph request per account. Trade-off: a person granted access purely
     * through an agency on an *owned* account is not picked up.
     *
     * @return array<int, string>
     */
    private function resolveBusinessIds(MetaGraphClient $client): array
    {
        if (! empty($this->adAccount->business_id)) {
            return [(string) $this->adAccount->business_id];
        }

        $ids = [];

        try {
            foreach ($client->paginated("{$this->adAccount->graphAccountId()}/agencies", ['fields' => 'id,name']) as $agency) {
                if (! empty($agency['id'])) {
                    $ids[] = (string) $agency['id'];
                }
            }
        } catch (MetaGraphException $e) {
            // No agency access is a normal state for a personal account, not a
            // sync failure — fall through to the "no business" outcome.
            Log::info('Meta agencies edge unreadable for ad account', [
                'ad_account_id' => $this->adAccount->id,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ]);
        }

        return array_values(array_unique($ids));
    }
}
