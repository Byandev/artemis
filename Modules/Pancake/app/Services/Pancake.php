<?php

namespace Modules\Pancake\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class Pancake
{
    public function __construct(public int $shop_id, public string $api_key) {}

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function listProducts(?string $params = '')
    {
        return Http::get('https://pos.pages.fm/api/v1/shops/'.$this->shop_id.'/orders?api_key='.$this->api_key."&$params")
            ->throw()
            ->json();
    }

    public function listCustomers(?string $params = '')
    {
        return Http::get('https://pos.pages.fm/api/v1/shops/'.$this->shop_id.'/customers?api_key='.$this->api_key."&$params")
            ->throw()
            ->json();
    }

    public function listUsers(?string $params = '')
    {
        return Http::get('https://pos.pages.fm/api/v1/shops/'.$this->shop_id.'/users?api_key='.$this->api_key."&$params")
            ->throw()
            ->json();
    }

    /**
     * Public API: GET /pages/{page_id}/page_customers
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public static function listPageCustomers(string $pageId, string $pageAccessToken, int $since, int $until, string $orderBy = 'inserted_at', int $pageNumber = 1, int $pageSize = 1): array
    {
        return Http::get("https://pages.fm/api/public_api/v1/pages/{$pageId}/page_customers", [
            'page_access_token' => $pageAccessToken,
            'page_number' => $pageNumber,
            'page_size' => $pageSize,
            'since' => $since,
            'until' => $until,
            'order_by' => $orderBy,
        ])->throw()->json();
    }

    /**
     * Public API (no key): GET /geo/provinces
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public static function listProvinces(int $countryCode = 63): array
    {
        return Http::retry(3, 1000)
            ->get('https://pos.pages.fm/api/v1/geo/provinces', ['country_code' => $countryCode])
            ->throw()
            ->json('data') ?? [];
    }

    /**
     * Public API (no key): GET /geo/districts
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public static function listDistricts(string $provinceId): array
    {
        return Http::retry(3, 1000)
            ->get('https://pos.pages.fm/api/v1/geo/districts', ['province_id' => $provinceId])
            ->throw()
            ->json('data') ?? [];
    }

    /**
     * Public API (no key): GET /geo/communes — every commune in the province.
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public static function listCommunes(string $provinceId): array
    {
        return Http::retry(3, 1000)
            ->get('https://pos.pages.fm/api/v1/geo/communes', ['province_id' => $provinceId])
            ->throw()
            ->json('data') ?? [];
    }
}
