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
use Modules\Pancake\Models\AddressAutofill;
use Modules\Pancake\Services\Pancake;
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

    /** One go: every attempt is a paid AI call. A failure is retried on Pancake's next webhook. */
    public int $tries = 1;

    public int $timeout = 90;

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

        $page = $record->page_id ? Page::find($record->page_id) : null;

        try {
            $result = $extract->fromConversation($record->page_id, $record->conversation_id, $page?->pancake_token);
        } catch (AddressExtractionFailed $e) {
            $this->finish($e->outcome, $e->getMessage());

            return;
        }

        if (! $result['found']) {
            $this->finish(AddressAutofill::NO_ADDRESS, 'The customer has not given an address in the chat.', $result);

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
            (new Pancake($shop->id, $shop->pos_token))->updateOrder($record->pancake_order_id, $update);
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

    /**
     * The order's shipping address with the location filled in. Name, phone and
     * anything else Pancake already had are carried over, so the update never
     * blanks them.
     */
    private function shippingAddress(array $result): array
    {
        $existing = (array) data_get($this->record->payload, 'shipping_address', []);

        return array_filter([
            ...array_intersect_key($existing, array_flip(['full_name', 'phone_number', 'country_code', 'post_code'])),
            'address' => $result['address'],
            'province_id' => $result['province']['id'],
            'district_id' => $result['district']['id'],
            'commune_id' => $result['commune']['id'],
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function finish(string $status, ?string $reason, ?array $result = null): void
    {
        $this->record->update([
            'status' => $status,
            'reason' => $reason ? mb_substr($reason, 0, 255) : null,
            'result' => $result,
            'processed_at' => now(),
        ]);
    }
}
