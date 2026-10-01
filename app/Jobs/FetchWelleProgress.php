<?php

namespace App\Jobs;

use App\Enums\IntegrationService;
use App\Exceptions\WelleAuthException;
use App\Models\User;
use App\Models\UserIntegration;
use App\Models\WelleDailyRecord;
use App\Services\Welle\WelleClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetch one user's progress from Welle and write each elapsed day to every
 * Welle-enabled workspace they belong to.
 *
 * With no window given it reads `progress/week` — the week containing today,
 * which is what the nightly run wants. Given a start date it reads
 * `progress/range` instead, which is the only way to reach a day the current
 * week no longer covers.
 */
class FetchWelleProgress implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public int $timeout = 120;

    /**
     * @param  string|null  $start  Y-m-d; without it, the week containing today.
     * @param  string|null  $end  Y-m-d; defaults to today when a start is given.
     */
    public function __construct(
        public int $userId,
        public ?string $start = null,
        public ?string $end = null,
    ) {
        $this->onQueue('welle');
    }

    /**
     * One read per user at a time.
     *
     * Two runs overlapping would write the same days over each other for no
     * gain, and would double this account's load on Welle.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("welle:user:{$this->userId}"))
                ->expireAfter(600)
                ->releaseAfter(30),
        ];
    }

    public function handle(WelleClient $client): void
    {
        $user = User::find($this->userId);
        $integration = $user?->integrationFor(IntegrationService::Welle);

        if (! $integration?->hasToken()) {
            return;
        }

        $workspaceIds = $user->workspaces()
            ->where('welle_module_enabled', true)
            ->pluck('workspaces.id');

        // Disconnected from every Welle workspace since the job was queued.
        if ($workspaceIds->isEmpty()) {
            return;
        }

        try {
            $window = $this->start === null
                ? $client->week($integration->token)
                : $client->range($integration->token, $this->start, $this->end ?? now()->toDateString());
        } catch (WelleAuthException $e) {
            // A revoked or expired token does not improve on a retry. Stop, say
            // so on the user, and let them reconnect from Settings.
            $this->recordFailure($integration, 'Welle rejected your token — reconnect your account.');

            Log::warning('Welle token rejected', ['user_id' => $user->getKey()]);

            return;
        }

        $days = $this->elapsedDays($window);

        foreach ($workspaceIds as $workspaceId) {
            foreach ($days as $day) {
                WelleDailyRecord::upsertDaily(
                    (int) $workspaceId,
                    (int) $user->getKey(),
                    (string) $day['date'],
                    $day,
                );
            }
        }

        // How the fetch went belongs to the connection that did it, not to the
        // user — a person may have connected several services and only one of
        // them be broken.
        $integration->forceFill([
            'last_synced_at' => now(),
            'last_error' => null,
        ])->save();
    }

    /**
     * The days of the week that have actually happened.
     *
     * Welle flags the rest as `is_future`, and storing them would put empty
     * days into the denominator of every rate — Friday's blank ring on a
     * Tuesday is a day still to come, not a day that was missed.
     *
     * @param  array<string, mixed>  $window
     * @return array<int, array<string, mixed>>
     */
    private function elapsedDays(array $window): array
    {
        return array_values(array_filter(
            $window['days'] ?? [],
            fn ($day) => is_array($day)
                && filled($day['date'] ?? null)
                && ! filter_var($day['is_future'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ));
    }

    /** The last retry gave up — leave the reason where the user can see it. */
    public function failed(Throwable $exception): void
    {
        $integration = User::find($this->userId)?->integrationFor(IntegrationService::Welle);

        if ($integration) {
            $this->recordFailure($integration, 'Could not reach Welle: '.$exception->getMessage());
        }
    }

    /**
     * Stamp the reason on the user for Settings → Integrations to show.
     * Truncated to the column width, and never carrying credentials.
     */
    private function recordFailure(UserIntegration $integration, string $message): void
    {
        $integration->forceFill(['last_error' => mb_substr($message, 0, 255)])->save();
    }
}
