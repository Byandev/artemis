<?php

namespace Modules\Pancake\Actions;

use App\Models\CallLog;
use App\Support\CallLogPersona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\Order;

/**
 * Hands an order that has just landed the day's calls that were waiting for it.
 *
 * App\Support\CallLogPersona makes this same match the other way round — a call
 * arrives and looks for an order taken in or confirmed that day — and it can
 * only find what pancake_orders already holds. The two feeds land on their own
 * schedules, so a verification call synced before its order is stamped unmatched
 * and would stay that way. Running the match from the order's side closes that
 * gap on the spot.
 *
 * Deliberately narrow: only calls carrying no order at all are claimed, only on
 * the day the order came in or was confirmed, and only to the number on its
 * shipping address. That is CallLogPersona::resolveVerification's rule read
 * backwards, so a call cannot end up matched here in a way the forward rule
 * would not.
 *
 * Narrower still, it runs for today's orders and no others. The gap it closes is
 * a same-day one — a call synced in the hours before its order — and every order
 * Pancake hands back re-syncs on the half hour, so without that bound this would
 * re-ask the same question of every order ever placed, on every cycle. Anything
 * older stays as the sync left it.
 */
class LinkVerificationCallLogsAction
{
    /** @return int calls claimed */
    public function execute(Order $savedOrder): int
    {
        $today = now()->toDateString();

        // Today's orders only, on either stamp: one taken in today has today's
        // calls to claim whether or not it has been confirmed yet, and one
        // confirmed today the same. An order whose day has already passed has
        // had its calls settled.
        $onToday = fn ($stamp) => ! empty($stamp) && Carbon::parse($stamp)->toDateString() === $today;

        if (! $onToday($savedOrder->confirmed_at) && ! $onToday($savedOrder->inserted_at)) {
            return 0;
        }

        // Read back rather than off the relation: the address is written a step
        // earlier in this same sync, so anything already loaded is stale.
        $phone = $savedOrder->shippingAddress()->value('phone_number');

        $key = CallLogPersona::normalize($phone);

        if ($key === null) {
            return 0;
        }

        return CallLog::query()
            ->where('workspace_id', $savedOrder->workspace_id)
            ->where('call_date', $today)
            ->whereNull('order_id')
            // CallLogPersona::normalize as SQL, so the day's rows are narrowed
            // by the database rather than read out and sifted here. The two
            // have to agree: last ten digits behind a leading zero, with
            // whatever punctuation either side was given stripped out. Anything
            // shorter than ten digits comes back shorter than a real key and so
            // matches nothing, which is what normalize's null does above.
            ->whereRaw("concat('0', right(regexp_replace(phone_number, '[^0-9]', ''), 10)) = ?", [$key])
            ->update([
                'order_id' => $savedOrder->id,
                // coalesce rather than a plain write: a call that somehow holds
                // a delivery persona without an order keeps it, because a
                // delivery match outranks a verification one.
                'persona' => DB::raw("coalesce(persona, '".CallLogPersona::VERIFICATION."')"),
                'updated_at' => now(),
            ]);
    }
}
