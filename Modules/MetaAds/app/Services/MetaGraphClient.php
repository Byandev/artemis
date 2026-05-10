<?php

namespace Modules\MetaAds\Services;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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
        $response = $this->request()->get($this->url($path), $query);

        return $this->decode($response);
    }

    /**
     * Paginated GET — yields each item from the `data` array, walking `paging.next` until exhausted.
     */
    public function paginated(string $path, array $query = []): Generator
    {
        $query = array_merge(['limit' => $this->pageSize], $query);
        $url = $this->url($path);

        while ($url !== null) {
            $response = $this->request()->get($url, $query);
            $body = $this->decode($response);

            foreach ($body['data'] ?? [] as $item) {
                yield $item;
            }

            $url = $body['paging']['next'] ?? null;
            // After the first request, `next` already contains the full URL with query + cursor.
            $query = [];
        }
    }

    /**
     * POST — used by Slice 2+ for write actions (e.g., budget changes). Slice 0 just defines it.
     */
    public function post(string $path, array $payload = []): array
    {
        $response = $this->request()->asForm()->post($this->url($path), $payload);

        return $this->decode($response);
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

    private function decode(Response $response): array
    {
        $body = $response->json() ?? [];

        if (isset($body['error'])) {
            throw MetaGraphException::fromResponseBody($body, $response->status());
        }

        if ($response->failed()) {
            throw new MetaGraphException(
                message: 'Meta Graph HTTP '.$response->status(),
                httpStatus: $response->status(),
            );
        }

        return $body;
    }
}
