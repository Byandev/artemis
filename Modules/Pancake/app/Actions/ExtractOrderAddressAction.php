<?php

namespace Modules\Pancake\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Modules\Pancake\Exceptions\AddressExtractionFailed;
use Modules\Pancake\Models\Commune;
use Modules\Pancake\Models\District;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\Province;
use Modules\Pancake\Services\ConversationAddressExtractor;
use Modules\Pancake\Services\Pancake;
use Modules\Pancake\Support\AddressMessageFilter;
use Modules\Pancake\Support\GeoMatcher;

/**
 * Order → its Messenger conversation → the address the customer typed → that
 * address split into parts and matched to Pancake's province / district /
 * commune ids. Read-only: writing it back is AutoFillOrderAddress's job.
 *
 * Works from a synced Order (the "Get address" button) or straight from the
 * page / conversation ids (the webhook, whose order may not be synced yet).
 *
 * Kept cheap three ways: only the messages that look like an address go to the
 * AI (AddressMessageFilter); a chat with none is "no address" without an AI
 * call at all (the webhook — the button still reads the tail); and the second,
 * shortlist call only runs for a level the names alone could not match.
 */
class ExtractOrderAddressAction
{
    /** How much of the chat is fetched and scanned — the address is almost always recent. */
    public const MAX_MESSAGES = 40;

    /** What the button reads when no message looks like an address. */
    public const FALLBACK_MESSAGES = 15;

    /** Shortlist checks per order — each is an AI call of its own. */
    public const MAX_PICKS = 2;

    /** A list longer than this is too costly to send and too long to pick from. */
    public const MAX_PICK_OPTIONS = 400;

    private const MAX_MESSAGE_LENGTH = 600;

    public function __construct(
        private readonly ConversationAddressExtractor $extractor,
        private readonly GeoMatcher $matcher,
        private readonly AddressMessageFilter $filter,
    ) {}

    /**
     * @throws AddressExtractionFailed
     */
    public function execute(Order $order): array
    {
        $order->loadMissing('page:id,pancake_token');

        return $this->fromConversation($order->page_id, $order->fb_id, $order->page?->pancake_token);
    }

    /**
     * @param  bool  $strict  true for the webhook: no address-like message means
     *                        "no address" without calling the AI. false for the
     *                        button, which then reads the tail of the chat.
     *
     * @throws AddressExtractionFailed
     */
    public function fromConversation(int|string|null $pageId, ?string $conversationId, ?string $pageToken, bool $strict = false): array
    {
        if (blank($conversationId) || blank($pageId)) {
            throw AddressExtractionFailed::noConversation();
        }

        if (blank($pageToken)) {
            throw AddressExtractionFailed::noPageToken();
        }

        $messages = $this->messages((string) $pageId, $conversationId, $pageToken);

        if (! collect($messages)->contains('from', 'customer')) {
            throw AddressExtractionFailed::emptyConversation();
        }

        $selected = $this->filter->select($messages);

        if ($selected === []) {
            if ($strict) {
                throw AddressExtractionFailed::noAddressLikeMessage();
            }

            $selected = array_slice($messages, -self::FALLBACK_MESSAGES);
        }

        $extracted = $this->extractor->extract($selected);
        $usage = $extracted['usage'];
        $pickedByAi = [];

        $match = $extracted['found']
            ? $this->matcher->match($extracted['province'], $extracted['city'], $extracted['barangay'])
            : $this->matcher->result(null, null, null);

        if ($extracted['found'] && $match['status'] !== 'complete') {
            [$match, $pickedByAi] = $this->shortlist($extracted, $match, $usage);
        }

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
            // Levels the names alone could not match, picked from the real list by the AI.
            'picked_by_ai' => $pickedByAi,
            'messages_read' => count($selected),
            'ai_usage' => $usage,
        ];
    }

    /**
     * Fill the levels the names could not match by asking the AI to choose from
     * the real options — top down, since each pick narrows the next list.
     *
     * @return array{0: array, 1: list<string>} the improved match, and which levels were picked
     */
    private function shortlist(array $extracted, array $match, array &$usage): array
    {
        $picks = 0;
        $picked = [];
        ['province' => $province, 'district' => $district, 'commune' => $commune] = $match;

        $ask = function (string $level, string $typed, array $options) use (&$picks, &$usage, $extracted): ?string {
            if ($picks >= self::MAX_PICKS || trim($typed) === '' || $options === [] || count($options) > self::MAX_PICK_OPTIONS) {
                return null;
            }

            $picks++;
            $answer = $this->extractor->pick($level, $typed, $extracted['address_text'], $options);
            $usage = self::addUsage($usage, $answer['usage']);

            return $answer['id'];
        };

        if (! $province) {
            $id = $ask('province', $extracted['province'] ?: $extracted['city'], $this->options(Province::where('country_code', 63)));

            if ($id && ($province = Province::find($id))) {
                $picked[] = 'province';
                $district = $this->matcher->districtIn($province, $extracted['city']);
            }
        }

        if ($province && ! $district) {
            $id = $ask('city or municipality', $extracted['city'], $this->options(District::where('province_id', $province->id)));

            if ($id && ($district = District::find($id))) {
                $picked[] = 'district';
                $commune = null;
            }
        }

        if ($district && ! $commune) {
            $commune = $this->matcher->communeIn($district, $extracted['barangay']);

            if (! $commune) {
                $id = $ask('barangay', $extracted['barangay'], $this->options(Commune::where('district_id', $district->id)));

                if ($id && ($commune = Commune::find($id))) {
                    $picked[] = 'commune';
                }
            }
        }

        return [$this->matcher->result($province, $district, $commune), $picked];
    }

    /** @return array<string, string> id => the name the customer would know */
    private function options($query): array
    {
        return $query->limit(self::MAX_PICK_OPTIONS + 1)->get(['id', 'name', 'name_en'])
            ->mapWithKeys(fn ($p) => [$p->id => $p->name_en ?: $p->name])
            ->all();
    }

    /**
     * Two calls' usage added together. A part stays null only when neither
     * call reported it.
     */
    public static function addUsage(array $a, array $b): array
    {
        $sum = fn (string $k) => ($a[$k] ?? null) === null && ($b[$k] ?? null) === null
            ? null
            : ($a[$k] ?? 0) + ($b[$k] ?? 0);

        return [
            'cost_usd' => $sum('cost_usd'),
            'input_tokens' => $sum('input_tokens'),
            'output_tokens' => $sum('output_tokens'),
        ];
    }

    /**
     * The conversation as plain text, oldest first, labelled by who wrote it.
     *
     * @return list<array{from: 'customer'|'page', text: string, at: ?string}>
     *
     * @throws AddressExtractionFailed
     */
    private function messages(string $pageId, string $conversationId, string $pageToken): array
    {
        try {
            $raw = Pancake::listConversationMessages($pageId, $conversationId, $pageToken);
        } catch (RequestException|ConnectionException $e) {
            Log::warning('Order address extraction: could not load the conversation.', [
                'conversation_id' => $conversationId,
                'reason' => mb_substr($e->getMessage(), 0, 300),
            ]);

            throw AddressExtractionFailed::conversationUnavailable();
        }

        return collect($raw)
            ->map(function (array $m) use ($pageId) {
                $text = (string) ($m['original_message'] ?? '');

                if ($text === '') {
                    $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</div>'], "\n", (string) ($m['message'] ?? ''))));
                }

                return [
                    'from' => (string) data_get($m, 'from.id') === $pageId ? 'page' : 'customer',
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
