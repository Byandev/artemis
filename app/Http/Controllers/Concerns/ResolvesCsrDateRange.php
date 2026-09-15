<?php

namespace App\Http\Controllers\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The date range every CSR stat card is read over, and the period its arrow is
 * measured against.
 *
 * Used by the CSR dashboard. The analytics endpoints in
 * API\Workspace\CSRController carry their own copy of both methods, and the
 * two must stay in step: a card on either page prints a `previous_period` on
 * hover, and the two pages disagreeing about what "the period before this one"
 * means would be visible on screen. Change one, change the other.
 */
trait ResolvesCsrDateRange
{
    /**
     * The requested range, defaulting to the last seven days, today included.
     *
     * @return array{0: string, 1: string}
     */
    protected function range(Request $request): array
    {
        $from = $request->input('from')
            ? CarbonImmutable::parse($request->input('from'))->toDateString()
            : CarbonImmutable::now()->subDays(6)->toDateString();

        $to = $request->input('to')
            ? CarbonImmutable::parse($request->input('to'))->toDateString()
            : CarbonImmutable::now()->toDateString();

        return [$from, $to];
    }

    /**
     * The equally long stretch ending the day before $from — so Aug 1–5 is
     * measured against Jul 27–31.
     *
     * @return array{0: string, 1: string}
     */
    protected function previousRange(string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from);
        $days = $start->diffInDays(CarbonImmutable::parse($to)) + 1;

        $previousTo = $start->subDay();

        return [$previousTo->subDays($days - 1)->toDateString(), $previousTo->toDateString()];
    }
}
