<?php

namespace Modules\Pancake\Jobs;

use App\Models\Page;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Modules\Pancake\Actions\ExtractOrderAddressAction;
use Modules\Pancake\Exceptions\AddressExtractionFailed;
use Modules\Pancake\Http\Controllers\AutoFillAddressWebhookController;
use Modules\Pancake\Models\AddressAutofill;
use Modules\Pancake\Services\Pancake;
use Modules\Pancake\Support\PancakeShippingAddress;
use Throwable;

/**
 * Reads a new order's address out of its Messenger conversation and writes it
 * back to the order in Pancake — province, district, commune ids and the
 * address line.
 *
 * Only writes when every level matched and the AI was sure enough; anything
 * less is left as needs_review for a person. With dry-run on (the default, see
 * config/config.php) it records what it would have sent and stops there.
 */
class AutoFillOrderAddress implements ShouldQueue
{
    use Queueable;

    /**
     * One go per read: every read is a paid AI call. A missing address is read
     * again on a delay (noAddressYet); a failure on Pancake's next webhook.
     */
    public int $tries = 1;

    public int $timeout = 90;

    /** The order as last loaded from Pancake — its shipping address is what the update builds on. */
    private array $current = [];

    public function __construct(public readonly AddressAutofill $record) {}

    public function handle(ExtractOrderAddressAction $extract): void
    {
        $record = $this->record;
        $shop = $record->shop;

        if (! $shop?->auto_fill_address) {
            $this->finish(AddressAutofill::SKIPPED, 'Auto-fill was turned off for this shop.');

            return;
        }

        if (blank($shop->pos_token)) {
            $this->finish(AddressAutofill::SKIPPED, 'The shop has no POS token.');

            return;
        }

        $record->increment('attempts');
        $pancake = new Pancake($shop->id, $shop->pos_token);

        // The order as Pancake has it now: minutes have passed since the
        // webhook, and a CSR may have confirmed it or typed the address.
        try {
            $order = $pancake->getOrder($record->pancake_order_id);
        } catch (RequestException|ConnectionException $e) {
            $this->finish(AddressAutofill::FAILED, 'Could not load the order from Pancake: '.mb_substr($e->getMessage(), 0, 150));

            return;
        }

        if ((int) ($order['status'] ?? -1) !== AutoFillAddressWebhookController::NEW_STATUS) {
            $this->finish(AddressAutofill::SKIPPED, 'The order is no longer new.');

            return;
        }

        if (filled(data_get($order, 'shipping_address.commune_id'))) {
            $this->finish(AddressAutofill::SKIPPED, 'The address was filled in meanwhile.');

            return;
        }

        $this->current = $order;
        $page = $record->page_id ? Page::find($record->page_id) : null;

        try {
            $result = $extract->fromConversation($record->page_id, $record->conversation_id, $page?->pancake_token);
        } catch (AddressExtractionFailed $e) {
            $e->outcome === AddressAutofill::NO_ADDRESS
                ? $this->noAddressYet($e->getMessage())
                : $this->finish($e->outcome, $e->getMessage());

            return;
        }

        if (! $result['found']) {
            $this->noAddressYet('The customer has not given an address in the chat.', $result);

            return;
        }

        $minConfidence = (float) config('pancake.auto_fill_address.min_confidence', 0.7);

        $doubt = match (true) {
            $result['status'] !== 'complete' => 'Not every level matched a Pancake location.',
            $result['address'] === '' => 'No street, purok or landmark was given.',
            $result['confidence'] < $minConfidence => 'The AI was not sure enough ('.round($result['confidence'] * 100).'%).',
            default => null,
        };

        if ($doubt) {
            $this->finish(AddressAutofill::NEEDS_REVIEW, $doubt, $result);

            return;
        }

        $update = ['shipping_address' => $this->shippingAddress($result)];

        if (config('pancake.auto_fill_address.dry_run', true)) {
            $this->finish(AddressAutofill::DRY_RUN, 'Dry run — Pancake was not changed.', [...$result, 'would_send' => $update]);

            return;
        }

        try {
            $pancake->updateOrder($record->pancake_order_id, $update);
        } catch (RequestException|ConnectionException $e) {
            Log::warning('Auto-fill order address: Pancake refused the update.', [
                'autofill_id' => $record->id,
                'reason' => mb_substr($e->getMessage(), 0, 500),
            ]);

            $this->finish(AddressAutofill::FAILED, 'Pancake refused the update: '.mb_substr($e->getMessage(), 0, 200), [...$result, 'sent' => $update]);

            return;
        }

        $this->finish(AddressAutofill::UPDATED, null, [...$result, 'sent' => $update]);
    }

    public function failed(?Throwable $e): void
    {
        $this->finish(AddressAutofill::FAILED, mb_substr((string) $e?->getMessage(), 0, 250));
    }

    private function shippingAddress(array $result): array
    {
        return PancakeShippingAddress::build(
            (array) data_get($this->current ?: $this->record->payload, 'shipping_address', []),
            $result['address'],
            $result['province']['id'],
            $result['district']['id'],
            $result['commune']['id'],
        );
    }

    /**
     * No address yet: read again later while reads are left, otherwise settle
     * on no_address (Pancake's next webhook for the order can still retry it).
     */
    private function noAddressYet(string $reason, ?array $result = null): void
    {
        $maxAttempts = (int) config('pancake.auto_fill_address.max_attempts', 2);

        if ($this->record->attempts >= $maxAttempts) {
            $this->finish(AddressAutofill::NO_ADDRESS, $reason, $result);

            return;
        }

        $at = now()->addMinutes((int) config('pancake.auto_fill_address.retry_minutes', 25));

        // Back to queued, so the webhook leaves it alone while it waits.
        $this->finish(AddressAutofill::QUEUED, $reason.' Reading again at '.$at->format('H:i').'.', $result);

        self::dispatch($this->record)->delay($at);
    }

    private function finish(string $status, ?string $reason, ?array $result = null): void
    {
        $usage = (array) data_get($result, 'ai_usage', []);
        $record = $this->record;

        $record->update([
            'status' => $status,
            'reason' => $reason ? mb_substr($reason, 0, 255) : null,
            'result' => $result,
            // Added up across reads, so the row shows what the order cost in
            // all. Untouched by a read that stopped before the AI call.
            'ai_cost_usd' => self::add($record->ai_cost_usd, $usage['cost_usd'] ?? null),
            'input_tokens' => self::add($record->input_tokens, $usage['input_tokens'] ?? null),
            'output_tokens' => self::add($record->output_tokens, $usage['output_tokens'] ?? null),
            'processed_at' => now(),
        ]);
    }

    private static function add(int|float|null $total, int|float|null $more): int|float|null
    {
        return $more === null ? $total : ($total ?? 0) + $more;
    }
}
