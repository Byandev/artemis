<?php

namespace App\Services\Sms;

use App\Models\Page;

/**
 * Resolves the SMS provider a page should send through, based on its
 * `sms_provider` column. Falls back to InfoTxt for existing pages (the column
 * defaults to `infotxt`), so behaviour is unchanged unless a page opts into
 * SendGate.
 */
class SmsProviderFactory
{
    public function for(Page $page): SmsProvider
    {
        return match ($page->sms_provider) {
            'sim_gateway' => new SimGatewayProvider(
                $page->sim_gateway_sim_id ? (int) $page->sim_gateway_sim_id : null,
            ),
            'sendgate' => new SendGateProvider(
                (string) $page->sendgate_api_key,
                (string) $page->sendgate_sim_id,
                (string) config('services.sendgate.base_url'),
            ),
            default => new InfoTxtProvider(
                (string) $page->infotxt_token,
                (string) $page->infotxt_user_id,
                (string) config('services.infotxt.base_url'),
            ),
        };
    }
}
