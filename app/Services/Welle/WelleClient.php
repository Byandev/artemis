<?php

namespace App\Services\Welle;

use App\Exceptions\WelleAuthException;
use App\Exceptions\WelleException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Talks to Welle on a user's behalf.
 *
 * Welle authenticates per account rather than with a shared API key. The user's
 * password is sent exactly once, when they connect from Settings → Integrations,
 * and exchanged for a Welle API token; only that token is kept. Nothing here
 * ever sees a password again.
 *
 * The one endpoint we read, `progress/week`, answers only for the week
 * containing today — Monday to Sunday on Welle's own display clock. There is no
 * date parameter, so this client cannot ask about an arbitrary past day, and
 * the fetch is per user rather than per user per date.
 */
class WelleClient
{
    private string $baseUrl;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.welle.base_url'), '/');
        $this->timeout = (int) config('services.welle.timeout', 30);
    }

    /** Whether a base URL is on file — without one there is nothing to call. */
    public static function isConfigured(): bool
    {
        return filled(config('services.welle.base_url'));
    }

    /**
     * Exchange credentials for a Welle API token.
     *
     * Called once, from the connect form. The password is a parameter and never
     * a property, so it lives no longer than this call.
     *
     * @throws WelleAuthException when the credentials are wrong.
     * @throws WelleException on any other unusable answer.
     */
    public function login(string $email, #[\SensitiveParameter] string $password): string
    {
        $response = $this->request()->post($this->url((string) config('services.welle.login_path')), [
            'email' => $email,
            'password' => $password,
            'device_name' => (string) config('services.welle.device_name', 'Artemis'),
        ]);

        // Welle answers a wrong password with a 422 validation error on `email`
        // rather than a 401, so 422 is an auth failure here — not a malformed
        // request. The request itself is built above and always complete.
        if (in_array($response->status(), [401, 403, 422], true)) {
            throw new WelleAuthException('Welle rejected the stored credentials.');
        }

        if (! $response->successful()) {
            throw WelleException::fromStatus('login', $response->status(), $response->body());
        }

        $token = data_get($response->json(), 'token');

        if (! is_string($token) || $token === '') {
            throw new WelleException('Welle accepted the login but returned no token.');
        }

        return $token;
    }

    /**
     * The current week's progress: Monday to Sunday, each day saying which of
     * the three pillars were logged, plus the user's streak figures.
     *
     * @return array{start: string, end: string, streak_days: int, longest_streak: int, days: array<int, array<string, mixed>>}
     */
    public function week(string $token): array
    {
        return $this->progress($token, (string) config('services.welle.progress_path'));
    }

    /**
     * The same shape over an arbitrary window, which is the only way to reach a
     * day the current week no longer covers.
     *
     * @param  string  $start  Y-m-d
     * @param  string  $end  Y-m-d
     * @return array{start: string, end: string, streak_days: int, longest_streak: int, days: array<int, array<string, mixed>>}
     */
    public function range(string $token, string $start, string $end): array
    {
        return $this->progress($token, (string) config('services.welle.range_path'), [
            'start' => $start,
            'end' => $end,
        ]);
    }

    /**
     * Read one progress window.
     *
     * @param  array<string, string>  $query
     * @return array{start: string, end: string, streak_days: int, longest_streak: int, days: array<int, array<string, mixed>>}
     *
     * @throws WelleAuthException when the token is no longer accepted.
     * @throws WelleException on any other unusable answer.
     */
    private function progress(string $token, string $path, array $query = []): array
    {
        $response = $this->request()->withToken($token)->get($this->url($path), $query);

        if (in_array($response->status(), [401, 403], true)) {
            throw new WelleAuthException('Welle rejected the stored token.');
        }

        if (! $response->successful()) {
            throw WelleException::fromStatus('progress', $response->status(), $response->body());
        }

        $window = data_get($response->json(), 'data');

        if (! is_array($window) || ! is_array($window['days'] ?? null)) {
            throw new WelleException('Welle returned a progress window without a days list.');
        }

        return $window;
    }

    private function request(): PendingRequest
    {
        if (! self::isConfigured()) {
            throw new WelleException('No Welle base URL configured (services.welle.base_url).');
        }

        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson();
    }

    private function url(string $path): string
    {
        return ltrim($path, '/');
    }
}
