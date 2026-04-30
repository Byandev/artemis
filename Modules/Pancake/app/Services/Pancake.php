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
    public static function listPageCustomers(string $pageId, string $pageAccessToken, int $since, int $until, int $pageNumber = 1, int $pageSize = 1): array
    {
        return Http::get("https://pages.fm/api/public_api/v1/pages/{$pageId}/page_customers", [
            'page_access_token' => $pageAccessToken,
            'page_number' => $pageNumber,
            'page_size' => $pageSize,
            'since' => $since,
            'until' => $until,
        ])->throw()->json();
    }
}
