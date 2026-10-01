<?php

namespace Modules\MetaAds\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\MetaAds\Models\AdAccount;
use Throwable;

/**
 * Resolves Meta's signed ad-preview iframe src for a single ad.
 *
 * Meta hands back an iframe even when the creative can't render in the format
 * we asked for — the failure only shows up as a "Story Unavailable" page
 * *inside* the frame, which the browser can't report back to us (cross-origin).
 * So the frame is fetched server-side and checked for that interstitial.
 *
 * Three things produce it, and the search is shaped to unpick them in order:
 *
 *  1. The creative only exists in another placement — Instagram, or a
 *     story/reel — so there is no feed story to render. Recovered in phase one
 *     by retrying a short list of other ad formats.
 *  2. The Meta user whose token signed the request has ads access to the
 *     account but no role on the page that owns the post, so Meta refuses to
 *     render it *for them*. Recovered in phase two by retrying through the
 *     account's other linked tokens — a colleague with the page role, or the
 *     Business Manager system user, renders the same ad fine.
 *  3. The page post behind the ad was deleted. Not recoverable; surfaces as a
 *     null src with the `story_unavailable` reason, and the drawer falls back
 *     to the creative we synced before it went away.
 *
 * The (token, format) pair that ends up rendering is cached per ad, so only the
 * first look-up pays for the search.
 */
class AdPreviewResolver
{
    /** Ad formats we're willing to ask Meta for, in fallback order. */
    public const FORMATS = [
        'MOBILE_FEED_STANDARD',
        'INSTAGRAM_STANDARD',
        'INSTAGRAM_STORY',
        'FACEBOOK_STORY_MOBILE',
        'DESKTOP_FEED_STANDARD',
    ];

    public const DEFAULT_FORMAT = 'MOBILE_FEED_STANDARD';

    /** Cache sentinel for "we checked every format and none rendered". */
    private const NONE = '__none__';

    /**
     * Needles from Meta's "Story Unavailable" interstitial, matched
     * case-insensitively against the frame HTML.
     */
    private const UNAVAILABLE_MARKERS = [
        'story unavailable',
        'story in this ad is unavailable',
        "isn't available right now",
        'content is no longer available',
    ];

    /**
     * Meta serves the preview frame differently to non-browser clients; send a
     * browser UA so what we validate is what the drawer will show.
     */
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    /**
     * @return array{src: string|null, format: string|null, requested_format: string, reason: string|null}
     *                                                                                                     `reason` is null when a preview rendered, `story_unavailable` when
     *                                                                                                     Meta rendered the interstitial for every token/format tried, and
     *                                                                                                     `no_preview` when Meta returned no iframe at all.
     */
    public function resolve(AdAccount $account, int|string $adId, string $format = self::DEFAULT_FORMAT): array
    {
        $format = $this->sanitizeFormat($format);
        $cacheKey = $this->cacheKey($adId, $format);
        $known = Cache::get($cacheKey);

        if ($known === self::NONE) {
            return $this->miss($format, 'story_unavailable');
        }

        $clients = $account->graphClients();

        if ($clients === []) {
            Log::warning('Meta ad preview has no usable token', ['ad_id' => (string) $adId, 'account_id' => (string) $account->id]);

            return $this->miss($format, 'no_preview');
        }

        // A previous look-up already worked out which token and format render.
        // Trust it and skip the search and the validation fetch — but still ask
        // Meta for a fresh src, because the `d=` token in it is short-lived and
        // never cached.
        if (is_array($known) && isset($clients[$known['client']])) {
            $src = $this->fetchSrc($clients[$known['client']], $adId, $known['format']);

            if ($src !== null) {
                return $this->hit($src, $known['format'], $format);
            }

            Cache::forget($cacheKey);
        }

        $primary = array_key_first($clients);
        $sawIframe = false;

        // Phase one — the account's primary token across the format candidates.
        // Catches the common case: the creative simply doesn't exist in the
        // placement we asked for.
        foreach ($this->candidates($format) as $candidate) {
            $src = $this->fetchSrc($clients[$primary], $adId, $candidate);

            if ($src === null) {
                continue;
            }

            $sawIframe = true;

            if ($this->renders($src)) {
                return $this->remember($cacheKey, $src, $candidate, $format, $primary);
            }
        }

        // Phase two — the same ad through the account's other tokens, requested
        // format only. An interstitial that follows the *signer* rather than the
        // format means no page role, and a token that has one renders it. Each
        // token has its own pacing clock, so these calls don't queue behind
        // phase one's.
        foreach ($this->alternates($clients, $primary) as $ref) {
            $src = $this->fetchSrc($clients[$ref], $adId, $format);

            if ($src === null) {
                continue;
            }

            $sawIframe = true;

            if ($this->renders($src)) {
                Log::info('Meta ad preview recovered through an alternate token', [
                    'ad_id' => (string) $adId,
                    'account_id' => (string) $account->id,
                    'token_ref' => $ref,
                ]);

                return $this->remember($cacheKey, $src, $format, $format, $ref);
            }
        }

        Cache::put($cacheKey, self::NONE, (int) config('metaads.preview.unavailable_ttl', 900));

        return $this->miss($format, $sawIframe ? 'story_unavailable' : 'no_preview');
    }

    /**
     * Tokens tried in phase two, capped so a heavily-shared account can't turn
     * one drawer open into a dozen Graph calls.
     *
     * @param  array<string, MetaGraphClient>  $clients
     * @return list<string>
     */
    private function alternates(array $clients, string $primary): array
    {
        $limit = max(0, (int) config('metaads.preview.alternate_tokens', 2));
        $refs = array_values(array_diff(array_keys($clients), [$primary]));

        return array_slice($refs, 0, $limit);
    }

    /** Cache the (token, format) pair that rendered, then return it. */
    private function remember(string $cacheKey, string $src, string $format, string $requested, string $client): array
    {
        Cache::put(
            $cacheKey,
            ['client' => $client, 'format' => $format],
            (int) config('metaads.preview.format_ttl', 21600),
        );

        return $this->hit($src, $format, $requested);
    }

    public function sanitizeFormat(?string $format): string
    {
        return in_array($format, self::FORMATS, true) ? $format : self::DEFAULT_FORMAT;
    }

    /**
     * The requested format first, then a capped slice of the rest. Each extra
     * candidate is another Graph call on a pacing-throttled token, so the list
     * stays short and the result is cached.
     *
     * @return list<string>
     */
    private function candidates(string $requested): array
    {
        $extra = max(0, (int) config('metaads.preview.fallback_attempts', 2));
        $rest = array_values(array_diff(self::FORMATS, [$requested]));

        return [$requested, ...array_slice($rest, 0, $extra)];
    }

    /**
     * Ask Meta for the iframe and pull the src out of it. Returns null when the
     * creative can't be rendered in this format at all — Meta reports that as a
     * Graph error rather than an empty response.
     */
    private function fetchSrc(MetaGraphClient $client, int|string $adId, string $format): ?string
    {
        try {
            $response = $client->get($adId.'/previews', ['ad_format' => $format]);
        } catch (Throwable $e) {
            // An incompatible format is expected and routine (Graph #100).
            // A revoked token or a deleted ad lands here too — worth a log line,
            // but it must not take the rest of the drawer down with it.
            Log::info('Meta ad preview request failed', [
                'ad_id' => (string) $adId,
                'format' => $format,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $body = $response['data'][0]['body'] ?? null;

        if ($body && preg_match('/src="([^"]+)"/', $body, $matches)) {
            return html_entity_decode($matches[1]);
        }

        return null;
    }

    /**
     * Fetch the frame and decide whether it actually rendered the ad. Fails
     * open: if Meta can't be reached, or answers with something other than a
     * page we recognise, we'd rather show a frame that might work than hide one
     * that does.
     */
    private function renders(string $src): bool
    {
        if (! config('metaads.preview.validate', true)) {
            return true;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout((int) config('metaads.preview.validate_timeout', 8))
                ->get($src);
        } catch (Throwable $e) {
            return true;
        }

        if ($response->failed()) {
            return true;
        }

        $html = mb_strtolower($response->body());

        foreach (self::UNAVAILABLE_MARKERS as $marker) {
            if (str_contains($html, $marker)) {
                return false;
            }
        }

        return true;
    }

    private function cacheKey(int|string $adId, string $format): string
    {
        return "metaads:preview-format:{$adId}:{$format}";
    }

    /** @return array{src: string, format: string, requested_format: string, reason: null} */
    private function hit(string $src, string $format, string $requested): array
    {
        return [
            'src' => $src,
            'format' => $format,
            'requested_format' => $requested,
            'reason' => null,
        ];
    }

    /** @return array{src: null, format: null, requested_format: string, reason: string} */
    private function miss(string $requested, string $reason): array
    {
        return [
            'src' => null,
            'format' => null,
            'requested_format' => $requested,
            'reason' => $reason,
        ];
    }
}
