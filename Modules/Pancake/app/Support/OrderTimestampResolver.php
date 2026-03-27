<?php

namespace Modules\Pancake\Support;

use Carbon\Carbon;

class OrderTimestampResolver
{
    /**
     * Resolve and convert all UTC timestamps in the raw order payload
     * to the application timezone. Also extracts confirmer metadata.
     */
    public function resolve(array $order): array
    {
        $insertedAt = Carbon::createFromFormat('Y-m-d\TH:i:s.u', $order['inserted_at'], 'UTC')
            ->setTimezone(config('app.timezone'))
            ->toDateTimeString();

        $history   = collect($order['status_history']);
        $confirmed = $history->firstWhere('status', 1);

        return [
            'inserted_at'  => $insertedAt,
            'confirmed_at' => $this->toLocal($history->firstWhere('status', 1)['updated_at'] ?? null),
            'shipped_at'   => $this->toLocal($history->firstWhere('status', 2)['updated_at'] ?? null),
            'delivered_at' => $this->toLocal($history->firstWhere('status', 3)['updated_at'] ?? null),
            'returning_at' => $this->toLocal($history->firstWhere('status', 4)['updated_at'] ?? null),
            'returned_at'  => $this->toLocal($history->firstWhere('status', 5)['updated_at'] ?? null),
            'conferrer_id' => $confirmed['editor_fb'] ?? null ?: null,
            'confirmed_by' => $confirmed['editor_id'] ?? null ?: null,
        ];
    }

    private function toLocal(?string $utc): ?string
    {
        if (! $utc) return null;

        return Carbon::createFromFormat('Y-m-d\TH:i:s', $utc, 'UTC')
            ->setTimezone(config('app.timezone'))
            ->toDateTimeString();
    }
}
