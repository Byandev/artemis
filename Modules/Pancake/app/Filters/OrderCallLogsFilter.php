<?php

namespace Modules\Pancake\Filters;

use App\Support\CallLogPersona;
use App\Support\RmoDailyStats;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Whether anyone in the workspace has called the number on the order, and — if
 * the question is narrowed — what kind of call it was.
 *
 * Matched on the number rather than on `call_logs.order_id`: a call only earns
 * an order id where the sync could place it — same-day, against a delivery or a
 * just-confirmed order — so plenty of calls to a customer sit next to their
 * order carrying nothing. The question here is about the customer's number, and
 * that is answered whether or not the call was ever pinned to this order.
 *
 * Both sides are reduced to the one spelling they can be compared on, because
 * neither is stored tidily: shipping_addresses keeps whatever Pancake was given
 * (+63, a leading zero, or neither) and the number a handset reports is no
 * better. Last ten digits behind a leading zero is App\Support\CallLogPersona's
 * rule, and the same SQL LinkVerificationCallLogsAction makes the match with —
 * the two have to agree, or an order would filter as uncalled here while that
 * action was busy claiming its calls.
 *
 * The call side is a DISTINCT subquery rather than a correlated EXISTS so the
 * workspace's numbers are normalized once per query instead of once per order.
 */
class OrderCallLogsFilter implements Filter
{
    use JoinsArrayValues;

    /** The number has been called at least once. */
    public const HAS = 'has';

    /** It has not — which includes an order carrying no address at all. */
    public const NONE = 'none';

    /** Both answers the filter takes, in the order the page offers them. */
    public const ANSWERS = [self::HAS, self::NONE];

    /** Any call at all, whatever the sync managed to make of it. */
    public const ANY = 'any';

    /** A delivery call: the customer or the rider on an RMO run. */
    public const RMO = 'rmo';

    /** A CSR ringing the customer before the order was ever loaded out. */
    public const VERIFICATION = 'verification';

    /** Every kind the filter takes, in the order the page offers them. */
    public const KINDS = [self::ANY, self::RMO, self::VERIFICATION];

    /**
     * @param  string  $kind  one of KINDS; anything else is read as ANY, so a
     *                        stale link still renders the list.
     */
    public function __construct(
        private readonly int $workspaceId,
        private readonly string $kind = self::ANY,
    ) {}

    public function __invoke(Builder $query, mixed $value, string $property): Builder
    {
        $answer = $this->whole($value);

        // Anything else narrows nothing rather than erroring: the value comes
        // off a query string, and a stale link should still render the list.
        if (! in_array($answer, self::ANSWERS, true)) {
            return $query;
        }

        [$persona, $bindings] = $this->personaClause();

        $called = fn (Builder $address) => $address->whereRaw(
            self::normalized('shipping_addresses.phone_number').' in ('
            .'select distinct '.self::normalized('call_logs.phone_number')
            .' from call_logs where call_logs.workspace_id = ?'.$persona.')',
            [$this->workspaceId, ...$bindings],
        );

        return $answer === self::HAS
            ? $query->whereHas('shippingAddress', $called)
            : $query->whereDoesntHave('shippingAddress', $called);
    }

    /**
     * The persona test for the chosen kind, and its bindings.
     *
     * RMO is the pair RmoDailyStats counts a day's calls by, read from there
     * rather than restated here so the filter and the RMO cards cannot drift
     * into disagreeing about what a delivery call is.
     *
     * A call the sync could place as neither carries a null persona and so
     * answers only ANY — which is right: it is a call to the number, but
     * nothing is known about what it was for.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function personaClause(): array
    {
        if ($this->kind === self::RMO) {
            $personas = RmoDailyStats::RMO_PERSONAS;
            $places = implode(', ', array_fill(0, count($personas), '?'));

            return [" and call_logs.persona in ({$places})", $personas];
        }

        if ($this->kind === self::VERIFICATION) {
            return [' and call_logs.persona = ?', [CallLogPersona::VERIFICATION]];
        }

        return ['', []];
    }

    /**
     * CallLogPersona::normalize as SQL. Fewer than ten digits comes back shorter
     * than a real key and so matches nothing, which is that method's null.
     */
    private static function normalized(string $column): string
    {
        return "concat('0', right(regexp_replace({$column}, '[^0-9]', ''), 10))";
    }
}
