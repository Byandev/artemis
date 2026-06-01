<?php

namespace Modules\MetaAds\Services;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\MetaAds\Exceptions\MetaGraphException;

class MetaGraphClient
{
    private string $baseUrl;

    private string $version;

    private int $pageSize;

    public function __construct(
        private readonly string $accessToken,
    ) {
        $this->baseUrl = rtrim((string) config('metaads.graph_base_url'), '/');
        $this->version = (string) config('metaads.graph_version');
        $this->pageSize = (int) config('metaads.page_size', 100);
    }

    /**
     * Single GET — returns the decoded JSON body.
     */
    public function get(string $path, array $query = []): array
    {
        $this->pacingSleep();
        $this->proactiveSleepIfNeeded();
        $response = $this->request()->get($this->url($path), $query);
        $this->stampPacingClock();
        $body = $this->safeDecode($response);
        $this->recordUsage($response);
        $this->throttleOnUsage($response);

        return $body;
    }

    /**
     * Single-page GET — fetches one page and returns the raw body (data + paging).
     * Pass an empty $query when $path is already a full `paging.next` URL (cursor is embedded).
     */
    public function getPage(string $path, array $query = []): array
    {
        if ($query !== []) {
            $query = array_merge(['limit' => $this->pageSize], $query);
        }

        $url = $this->url($path);

        $this->pacingSleep();
        $this->proactiveSleepIfNeeded();
        $response = $this->request()->get($url, $query);
        $this->stampPacingClock();
        $body = $this->safeDecode($response);
        $this->recordUsage($response);
        $this->throttleOnUsage($response);

        return $body;
    }

    /**
     * Paginated GET — yields each item from the `data` array, walking `paging.next` until exhausted.
     */
    public function paginated(string $path, array $query = []): Generator
    {
        $query = array_merge(['limit' => $this->pageSize], $query);
        $url = $this->url($path);

        while ($url !== null) {
            dump($url);
            $this->pacingSleep();
            $this->proactiveSleepIfNeeded();
            $response = $this->request()->get($url, $query);
            $this->stampPacingClock();
            $body = $this->safeDecode($response);

            foreach ($body['data'] ?? [] as $item) {
                yield $item;
            }

            $url = $body['paging']['next'] ?? null;
            // After the first request, `next` already contains the full URL with query + cursor.
            $query = [];

            $this->recordUsage($response);
            // Sleep AFTER yielding the page so the consumer makes progress before we pause.
            $this->throttleOnUsage($response);
        }
    }

    /**
     * POST — used by Slice 2+ for write actions (e.g., budget changes). Slice 0 just defines it.
     */
    public function post(string $path, array $payload = []): array
    {
        $this->pacingSleep();
        $this->proactiveSleepIfNeeded();
        $response = $this->request()->asForm()->post($this->url($path), $payload);
        $this->stampPacingClock();
        $body = $this->safeDecode($response);
        $this->recordUsage($response);
        $this->throttleOnUsage($response);

        return $body;
    }

    public int $timeoutSeconds = 120;

    private function request(): PendingRequest
    {
        return Http::withToken($this->accessToken)
            ->acceptJson()
            ->timeout($this->timeoutSeconds)
            ->retry(2, 500, function ($exception, $request) {
                // Retry on transport errors only; HTTP-level errors are surfaced via decode().
                return $exception instanceof ConnectionException;
            }, throw: false);
    }

    private function url(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return $this->baseUrl.'/'.$this->version.'/'.ltrim($path, '/');
    }

    /**
     * Decode the response and, when Meta returns a rate-limit error, record
     * the token as "saturated" in the shared cache and re-throw with Meta's
     * own estimated reset time attached so the queue worker can wait the
     * right amount instead of guessing.
     */
    private function safeDecode(Response $response): array
    {
        try {
            $body = $response->json() ?? [];

            if (isset($body['error'])) {
                $resetHint = $this->extractResetHint($response);
                throw MetaGraphException::fromResponseBody($body, $response->status(), $resetHint);
            }

            if ($response->failed()) {
                throw new MetaGraphException(
                    message: 'Meta Graph HTTP '.$response->status(),
                    httpStatus: $response->status(),
                );
            }

            return $body;
        } catch (MetaGraphException $e) {
            if ($e->isRateLimited()) {
                $ttl = max(60, (int) ($e->resetSeconds ?? 300));
                Cache::put($this->usageCacheKey(), 100, $ttl);
            }

            throw $e;
        }
    }

    /**
     * Pull `estimated_time_to_regain_access` from any of the BUC product
     * buckets on a rate-limit response. Meta usually returns minutes; we
     * convert to seconds. Returns null if absent.
     */
    private function extractResetHint(Response $response): ?int
    {
        $hints = [];

        $buc = $response->header('x-business-use-case-usage');
        if ($buc) {
            $decoded = json_decode($buc, true) ?: [];
            foreach ($decoded as $buckets) {
                foreach ((array) $buckets as $bucket) {
                    if (isset($bucket['estimated_time_to_regain_access']) && is_numeric($bucket['estimated_time_to_regain_access'])) {
                        $hints[] = (int) $bucket['estimated_time_to_regain_access'];
                    }
                }
            }
        }

        $accUsage = $response->header('x-ad-account-usage');
        if ($accUsage) {
            $decoded = json_decode($accUsage, true) ?: [];
            // reset_time_duration is already in seconds.
            if (isset($decoded['reset_time_duration']) && is_numeric($decoded['reset_time_duration'])) {
                return (int) $decoded['reset_time_duration'];
            }
        }

        if ($hints === []) {
            return null;
        }

        // BUC's estimated_time_to_regain_access is documented in minutes.
        return max($hints) * 60;
    }

    /**
     * Sleep before firing a request if the shared cache says this token is
     * already near or over Meta's rate-limit thresholds. Each worker that
     * gets a fresh usage reading writes it into the cache (see recordUsage),
     * so this is the cross-job coordination that single-job reactive sleep
     * can't provide.
     */
    private function proactiveSleepIfNeeded(): void
    {
        $worst = Cache::get($this->usageCacheKey());

        if ($worst === null) {
            return;
        }

        $soft = (int) config('metaads.throttle.soft_threshold', 75);
        $softSleep = (int) config('metaads.throttle.soft_sleep_seconds', 10);
        $hard = (int) config('metaads.throttle.hard_threshold', 95);
        $hardSleep = (int) config('metaads.throttle.hard_sleep_seconds', 60);

        $worst = (int) $worst;

        if ($worst >= $hard) {
            Log::warning('Meta Graph cached usage >= hard threshold, proactive sleep', ['score' => $worst, 'sleep' => $hardSleep]);
            sleep($hardSleep);

            return;
        }

        if ($worst >= $soft) {
            Log::info('Meta Graph cached usage >= soft threshold, proactive sleep', ['score' => $worst, 'sleep' => $softSleep]);
            sleep($softSleep);
        }
    }

    private function recordUsage(Response $response): void
    {
        $worst = $this->worstUsageScore($response);

        if ($worst === null) {
            return;
        }

        // 120s TTL covers Meta's rolling per-user/app/buc windows long enough
        // for peer workers to see the score, without pinning stale values
        // after the bucket has reset.
        Cache::put($this->usageCacheKey(), $worst, 120);
    }

    private function usageCacheKey(): string
    {
        return 'meta_graph:usage:'.hash('sha256', $this->accessToken);
    }

    private function pacingCacheKey(): string
    {
        return 'meta_graph:pace:'.hash('sha256', $this->accessToken);
    }

    /**
     * Enforce a minimum gap between successive requests on the same token.
     * On Limited Access (max score 60 / 300s ≈ 12 calls/min) bursts trip the
     * cap instantly even when usage headers look healthy — pacing prevents
     * the burst from ever happening across workers sharing this token.
     */
    private function pacingSleep(): void
    {
        $minInterval = (int) config('metaads.throttle.min_interval_seconds', 0);

        if ($minInterval <= 0) {
            return;
        }

        $last = Cache::get($this->pacingCacheKey());

        if ($last === null) {
            return;
        }

        $elapsed = microtime(true) - (float) $last;
        $wait = $minInterval - $elapsed;

        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
    }

    private function stampPacingClock(): void
    {
        if ((int) config('metaads.throttle.min_interval_seconds', 0) <= 0) {
            return;
        }

        Cache::put($this->pacingCacheKey(), microtime(true), 300);
    }

    /**
     * Inspect Meta's usage headers and sleep when we get close to the rate limit.
     * Looks at the worst score across call_count / total_cputime / total_time
     * inside x-app-usage, x-ad-account-usage, and every BUC bucket of
     * x-business-use-case-usage. Soft threshold → short pause, hard → longer.
     */
    private function throttleOnUsage(Response $response): void
    {
        $soft = (int) config('metaads.throttle.soft_threshold', 75);
        $softSleep = (int) config('metaads.throttle.soft_sleep_seconds', 10);
        $hard = (int) config('metaads.throttle.hard_threshold', 95);
        $hardSleep = (int) config('metaads.throttle.hard_sleep_seconds', 60);

        $worst = $this->worstUsageScore($response);

        if ($worst === null) {
            return;
        }

        if ($worst >= $hard) {
            Log::warning('Meta Graph usage hit hard threshold', ['score' => $worst, 'sleep' => $hardSleep]);
            sleep($hardSleep);

            return;
        }

        if ($worst >= $soft) {
            Log::info('Meta Graph usage hit soft threshold', ['score' => $worst, 'sleep' => $softSleep]);
            sleep($softSleep);
        }
    }

    private function worstUsageScore(Response $response): ?int
    {
        $scores = [];

        foreach (['x-app-usage', 'x-ad-account-usage'] as $header) {
            $value = $response->header($header);

            if ($value) {
                $decoded = json_decode($value, true) ?: [];
                $scores = array_merge($scores, $this->extractScores($decoded));
            }
        }

        $buc = $response->header('x-business-use-case-usage');

        if ($buc) {
            $decoded = json_decode($buc, true) ?: [];
            foreach ($decoded as $buckets) {
                foreach ((array) $buckets as $bucket) {
                    $scores = array_merge($scores, $this->extractScores((array) $bucket));
                }
            }
        }

        // Ads Insights has its own throttle bucket (X-FB-Ads-Insights-Throttle)
        // that's reported separately from BUC. Without reading it, insights
        // jobs are completely blind to their dedicated rate-limit pool.
        $insights = $response->header('x-fb-ads-insights-throttle');
        if ($insights) {
            $decoded = json_decode($insights, true) ?: [];
            $scores = array_merge($scores, $this->extractScores($decoded));
        }

        return $scores === [] ? null : (int) max($scores);
    }

    private function extractScores(array $payload): array
    {
        // call_count/total_cputime/total_time are BUC fields (percentages 0-100).
        // app_id_util_pct/acc_id_util_pct are the X-FB-Ads-Insights-Throttle and
        // X-Ad-Account-Usage fields (also percentages 0-100).
        $keys = ['call_count', 'total_cputime', 'total_time', 'app_id_util_pct', 'acc_id_util_pct'];
        $scores = [];

        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                $scores[] = (int) $payload[$key];
            }
        }

        return $scores;
    }
}
