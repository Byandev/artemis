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
}
