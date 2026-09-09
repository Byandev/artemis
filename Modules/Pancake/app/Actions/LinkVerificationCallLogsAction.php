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
 * arrives and looks for an order confirmed that day — and it can only find what
 * pancake_orders already holds. The two feeds land on their own schedules, so a
 * verification call synced before its order is stamped unmatched and stays that
 * way until the nightly backfill comes past. Running it from the order's side
 * closes that gap on the spot.
 *
 * Deliberately narrow: only calls carrying no order at all are claimed, only on
 * the day the order was confirmed, and only to the number on its shipping
 * address. That is CallLogPersona::resolveVerification's rule read backwards,
 * so a call cannot end up matched here in a way the forward rule would not.
 *
 * Narrower still, it runs for orders confirmed today and no others. The gap it
 * closes is a same-day one — a call synced in the hours before its order — and
 * every order Pancake hands back re-syncs on the half hour, so without that
 * bound this would re-ask the same question of every order ever confirmed, on
 * every cycle. Anything older is the nightly backfill's to settle.
 */
class LinkVerificationCallLogsAction
{
    /** @return int calls claimed */
    public function execute(Order $savedOrder): int
    {
        $today = now()->toDateString();

        // Today's confirmations only. An unconfirmed order has no day to claim
        // in the first place — a call is a verification call by virtue of the
        // day the order was confirmed on — and one confirmed earlier has had
        // its calls settled already.
        $confirmedOn = empty($savedOrder->confirmed_at)
            ? null
            : Carbon::parse($savedOrder->confirmed_at)->toDateString();

        if ($confirmedOn !== $today) {
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
            ->where('call_date', $confirmedOn)
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
