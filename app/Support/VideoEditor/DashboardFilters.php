<?php

namespace App\Support\VideoEditor;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Immutable filter set for the video-editor dashboard, shared by every
 * section. Parses raw request input once into typed values and clamps the
 * throughput grouping to one valid for the selected range.
 */
final class DashboardFilters
{
    private const GROUP_DAILY = 'daily';

    private const GROUP_WEEKLY = 'weekly';

    private const GROUP_MONTHLY = 'monthly';

    private const GROUP_YEARLY = 'yearly';

    public function __construct(
        public readonly CarbonImmutable $dateFrom,
        public readonly CarbonImmutable $dateTo,
        public readonly ?int $productId,
        public readonly ?string $format,
        public readonly string $group,
    ) {}

    public static function fromRequest(Request $request): self
    {
        // Date range mirrors the main dashboard: month-to-date by default.
        $dateFrom = $request->filled('date_from')
            ? CarbonImmutable::parse($request->date('date_from'))
            : CarbonImmutable::today()->startOfMonth();

        $dateTo = $request->filled('date_to')
            ? CarbonImmutable::parse($request->date('date_to'))
            : CarbonImmutable::today();

        $productId = $request->filled('product_id') && $request->input('product_id') !== 'all'
            ? (int) $request->input('product_id')
            : null;

        $format = in_array($request->input('format'), ['video', 'image'], true)
            ? $request->string('format')->toString()
            : null;

        return new self(
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            productId: $productId,
            format: $format,
            group: self::resolveGroup($request->input('group'), $dateFrom, $dateTo),
        );
    }

    /**
     * @return array{from: string, to: string}
     */
    public function dateBounds(): array
    {
        return [
            'from' => $this->dateFrom->toDateString(),
            'to' => $this->dateTo->toDateString(),
        ];
    }

    /**
     * Filter payload shared with the frontend (string-friendly).
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'date_from' => $this->dateFrom->toDateString(),
            'date_to' => $this->dateTo->toDateString(),
            'product_id' => $this->productId ? (string) $this->productId : 'all',
            'format' => $this->format ?? 'all',
            'group' => $this->group,
        ];
    }

    /**
     * Grouping options that make sense for a given range, mirroring the main
     * dashboard's breakdown toggle.
     *
     * @return list<string>
     */
    private static function availableGroups(CarbonImmutable $dateFrom, CarbonImmutable $dateTo): array
    {
        return match (true) {
            $dateFrom->diffInDays($dateTo) <= 7 => [self::GROUP_DAILY],
            $dateFrom->diffInDays($dateTo) <= 30 => [self::GROUP_DAILY, self::GROUP_WEEKLY],
            $dateFrom->diffInDays($dateTo) <= 365 => [self::GROUP_WEEKLY, self::GROUP_MONTHLY],
            default => [self::GROUP_WEEKLY, self::GROUP_MONTHLY, self::GROUP_YEARLY],
        };
    }

    /**
     * Clamp the requested grouping to one valid for the selected range,
     * defaulting to the finest available grouping.
     */
    private static function resolveGroup(?string $group, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): string
    {
        $available = self::availableGroups($dateFrom, $dateTo);

        return in_array($group, $available, true) ? $group : $available[0];
    }
}
