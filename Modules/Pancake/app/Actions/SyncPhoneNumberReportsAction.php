<?php

namespace Modules\Pancake\Actions;

use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderPhoneNumberReport;
use Modules\Pancake\Models\PhoneNumberReport;

class SyncPhoneNumberReportsAction
{
    public function execute(Order $savedOrder, array $order): void
    {
        if (empty($order['reports_by_phone'])) return;

        foreach ($order['reports_by_phone'] as $phone => $report) {
            $reportData = [
                'order_fail'    => $report['order_fail'] ?? 0,
                'order_success' => $report['order_success'] ?? 0,
                'warning'       => $report['warning'] ?? 0,
            ];

            if (array_sum($reportData) === 0) continue;

            $normalizedPhone = '0' . substr((string) $phone, -10);

            // Global cumulative snapshot — always reflects latest API state
            PhoneNumberReport::updateOrCreate(
                ['phone_number' => $normalizedPhone],
                $reportData
            );

            // Initial snapshot — locked at first creation, never overwritten
            OrderPhoneNumberReport::firstOrCreate(
                ['order_id' => $savedOrder->id, 'phone_number' => $normalizedPhone, 'type' => 'initial'],
                $reportData
            );

            // Latest snapshot — always reflects current API state
            OrderPhoneNumberReport::updateOrCreate(
                ['order_id' => $savedOrder->id, 'phone_number' => $normalizedPhone, 'type' => 'latest'],
                $reportData
            );
        }
    }
}
