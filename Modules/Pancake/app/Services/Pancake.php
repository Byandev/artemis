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
     * Fetch per-user customer engagement statistics for a page on the given day.
     * Uses the pages.fm public_api endpoint which authenticates with the page's pancake_token.
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public static function getCustomerEngagements(int $pageId, string $pageAccessToken, \Carbon\Carbon $date): array
    {
        $start = $date->copy()->startOfDay()->format('d/m/Y H:i:s');
        $end = $date->copy()->addDay()->startOfDay()->format('d/m/Y H:i:s');

        $pageId = 729182576936579;
        $pageAccessToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6IjcyOTE4MjU3NjkzNjU3OSIsInRpbWVzdGFtcCI6MTc3MTYzOTg3N30.du_UE9R5aThgIJ5Dqv_NmMopUprCSQIoMnG167Ucu50';

        return Http::get("https://pages.fm/api/public_api/v1/pages/{$pageId}/statistics/customer_engagements", [
            'page_access_token' => $pageAccessToken,
            'date_range' => "$start - $end",
            'by_hour' => 'false',
        ])
            ->throw()
            ->json();
    }
}
