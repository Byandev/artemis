<?php

namespace Modules\Pancake\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Modules\Pancake\Exceptions\AddressExtractionFailed;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Services\ConversationAddressExtractor;
use Modules\Pancake\Services\Pancake;
use Modules\Pancake\Support\GeoMatcher;

/**
 * Order → its Messenger conversation → the address the customer typed → that
 * address split into parts and matched to Pancake's province / district /
 * commune ids. Read-only: nothing is saved or sent back to Pancake yet.
 */
class ExtractOrderAddressAction
{
    /** Only the tail of a long chat is read — the address is almost always recent. */
    public const MAX_MESSAGES = 40;

    private const MAX_MESSAGE_LENGTH = 600;

    public function __construct(
        private readonly ConversationAddressExtractor $extractor,
        private readonly GeoMatcher $matcher,
    ) {}

    /**
     * @throws AddressExtractionFailed
     */
    public function execute(Order $order): array
    {
        $order->loadMissing('page:id,pancake_token');

        if (blank($order->fb_id) || ! $order->page_id) {
            throw AddressExtractionFailed::noConversation();
        }

        if (blank($order->page?->pancake_token)) {
            throw AddressExtractionFailed::noPageToken();
        }

        $messages = $this->messages($order);

        if (! collect($messages)->contains('from', 'customer')) {
            throw AddressExtractionFailed::emptyConversation();
        }

        $extracted = $this->extractor->extract($messages);

        $match = $extracted['found']
            ? $this->matcher->match($extracted['province'], $extracted['city'], $extracted['barangay'])
            : ['province' => null, 'district' => null, 'commune' => null, 'status' => 'not_found'];

        // The detail line Pancake keeps beside the three ids: street / purok /
        // house number, with the landmark after it when there is one.
        $address = collect([$extracted['street'], $extracted['landmark']])->filter()->implode(', ');

        $level = fn (string $typed, $place) => [
            'typed' => $typed,
            'id' => $place?->id,
            'name' => $place?->name,
        ];

        return [
            'found' => $extracted['found'],
            'status' => $match['status'],
            'confidence' => $extracted['confidence'],
            'address_text' => $extracted['address_text'],
            'province' => $level($extracted['province'], $match['province']),
            'district' => $level($extracted['city'], $match['district']),
            'commune' => $level($extracted['barangay'], $match['commune']),
            'address' => $address,
            // Pancake's own full_address shape: address, commune, district, province.
            'formatted_address' => collect([
                $address,
                $match['commune']?->name,
                $match['district']?->name,
                $match['province']?->name,
            ])->filter()->implode(', '),
            'messages_read' => count($messages),
        ];
    }

    /**
     * The conversation as plain text, oldest first, labelled by who wrote it.
     *
     * @return list<array{from: 'customer'|'page', text: string, at: ?string}>
     *
     * @throws AddressExtractionFailed
     */
    private function messages(Order $order): array
    {
        try {
            $raw = Pancake::listConversationMessages((string) $order->page_id, $order->fb_id, $order->page->pancake_token);
        } catch (RequestException|ConnectionException $e) {
            Log::warning('Order address extraction: could not load the conversation.', [
                'order_id' => $order->id,
                'reason' => mb_substr($e->getMessage(), 0, 300),
            ]);

            throw AddressExtractionFailed::conversationUnavailable();
        }

        return collect($raw)
            ->map(function (array $m) use ($order) {
                $text = (string) ($m['original_message'] ?? '');

                if ($text === '') {
                    $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</div>'], "\n", (string) ($m['message'] ?? ''))));
                }

                return [
                    'from' => (string) data_get($m, 'from.id') === (string) $order->page_id ? 'page' : 'customer',
                    'text' => mb_substr(trim($text), 0, self::MAX_MESSAGE_LENGTH),
                    'at' => $m['inserted_at'] ?? null,
                ];
            })
            ->filter(fn (array $m) => $m['text'] !== '')
            ->sortBy('at')
            ->take(-self::MAX_MESSAGES)
            ->values()
            ->all();
    }
}
