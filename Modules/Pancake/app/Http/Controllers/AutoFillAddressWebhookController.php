<?php

namespace Modules\Pancake\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Jobs\AutoFillOrderAddress;
use Modules\Pancake\Models\AddressAutofill;

/**
 * Pancake POS → Settings → Advanced → Webhook/API, with Data set to Orders.
 *
 * Pancake posts every order change here, so most calls are ignored on sight:
 * only a new order (status 0) with a conversation and no commune yet is worth
 * reading. Those are recorded and queued; the reply goes back straight away so
 * Pancake never waits on the chat or the AI.
 *
 * Authenticated by the shop's webhook_secret in the X-Artemis-Secret header —
 * created when auto-fill is switched on (Shops → Edit).
 */
class AutoFillAddressWebhookController extends Controller
{
    public const NEW_STATUS = 0;

    public function __invoke(Request $request, Shop $shop): JsonResponse
    {
        if (blank($shop->webhook_secret)
            || ! hash_equals($shop->webhook_secret, (string) $request->header('X-Artemis-Secret'))) {
            return response()->json(['message' => 'Invalid webhook secret.'], 401);
        }

        // Every ignored call still answers 200: a non-2xx makes Pancake send
        // error-alert emails, and none of these are errors.
        if (! $shop->auto_fill_address) {
            return $this->ignored('Auto-fill is off for this shop.');
        }

        $order = $this->order($request->all());

        if (! $order) {
            return $this->ignored('Not an order.');
        }

        if ((string) ($order['shop_id'] ?? $shop->id) !== (string) $shop->id) {
            return $this->ignored('Order belongs to another shop.');
        }

        if ((int) ($order['status'] ?? -1) !== self::NEW_STATUS) {
            return $this->ignored('Not a new order.');
        }

        if (filled(data_get($order, 'shipping_address.commune_id'))) {
            return $this->ignored('Order already has a full address.');
        }

        if (blank($order['conversation_id'] ?? null)) {
            return $this->ignored('Order has no Messenger conversation.');
        }

        $record = $this->claim($shop, $order);

        if (! $record) {
            return $this->ignored('Already handled.');
        }

        // Not read straight away: the order is often created before the
        // customer has typed the address.
        AutoFillOrderAddress::dispatch($record)
            ->delay(now()->addSeconds((int) config('pancake.auto_fill_address.delay_seconds', 15)));

        return response()->json(['status' => 'queued']);
    }

    /**
     * The order out of the body — sent as the order itself, or wrapped in
     * `data`. Null when neither looks like an order.
     */
    private function order(array $body): ?array
    {
        foreach ([$body['data'] ?? null, $body] as $candidate) {
            if (is_array($candidate) && isset($candidate['id']) && array_key_exists('status', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Record the order as queued, once. Pancake often sends a new order more
     * than once in quick succession; the row lock and the unique key keep that
     * to one AI call. An earlier no_address / failed attempt may go again.
     */
    private function claim(Shop $shop, array $order): ?AddressAutofill
    {
        $attributes = [
            'page_id' => isset($order['page_id']) ? (string) $order['page_id'] : null,
            'conversation_id' => (string) $order['conversation_id'],
            'status' => AddressAutofill::QUEUED,
            'reason' => null,
            'attempts' => 0,
            'payload' => $order,
        ];

        try {
            return DB::transaction(function () use ($shop, $order, $attributes) {
                $existing = AddressAutofill::where('shop_id', $shop->id)
                    ->where('pancake_order_id', (string) $order['id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing && ! in_array($existing->status, AddressAutofill::RETRYABLE, true)) {
                    return null;
                }

                if ($existing) {
                    $existing->update($attributes);

                    return $existing;
                }

                return AddressAutofill::create([
                    'shop_id' => $shop->id,
                    'pancake_order_id' => (string) $order['id'],
                    ...$attributes,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    private function ignored(string $reason): JsonResponse
    {
        return response()->json(['status' => 'ignored', 'reason' => $reason]);
    }
}
