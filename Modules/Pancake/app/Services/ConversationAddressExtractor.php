<?php

namespace Modules\Pancake\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Pancake\Exceptions\AddressExtractionFailed;

/**
 * Reads a customer's delivery address out of their Messenger conversation.
 *
 * The model only splits the address into parts (barangay, city, province…);
 * turning those names into Pancake's location ids is GeoMatcher's job, against
 * our own copy of Pancake's list, so a wrong guess never becomes an id.
 *
 * Talks to OpenRouter over the HTTP client, the same way the Products module's
 * ProductResearchNameSuggester does — see config/openrouter.php.
 */
class ConversationAddressExtractor
{
    private const FIELDS = [
        'found', 'address_text', 'street', 'barangay', 'city', 'province',
        'landmark', 'confidence',
    ];

    private string $model;

    private int $timeout;

    public function __construct()
    {
        $this->model = (string) config('openrouter.address_extraction_model', 'openai/gpt-4o-mini');
        $this->timeout = (int) config('openrouter.request_timeout', 30);
    }

    public static function isConfigured(): bool
    {
        return filled(config('openrouter.api_key'));
    }

    /**
     * @param  list<array{from: 'customer'|'page', text: string, at: ?string}>  $messages  oldest first
     * @return array{found: bool, address_text: string, street: string, barangay: string, city: string, province: string, landmark: string, confidence: float}
     *
     * @throws AddressExtractionFailed
     */
    public function extract(array $messages): array
    {
        if (! self::isConfigured()) {
            throw AddressExtractionFailed::aiNotConfigured();
        }

        $transcript = collect($messages)
            ->map(fn (array $m) => ($m['from'] === 'customer' ? 'Customer' : 'Page').': '.$m['text'])
            ->implode("\n");

        try {
            $response = $this->request()->post('/chat/completions', $this->payload($transcript));
        } catch (ConnectionException $e) {
            Log::warning('Order address extraction: could not reach the provider.', ['reason' => $e->getMessage()]);

            throw AddressExtractionFailed::aiUnavailable();
        }

        if (! $response->successful()) {
            Log::warning('Order address extraction: the provider refused the request.', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw AddressExtractionFailed::aiUnavailable($response->status());
        }

        $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content'), true);

        if (! is_array($decoded)) {
            Log::warning('Order address extraction: the answer was not JSON.', [
                'finish_reason' => data_get($response->json(), 'choices.0.finish_reason'),
            ]);

            throw AddressExtractionFailed::aiUnusableAnswer();
        }

        $result = [];

        foreach (self::FIELDS as $field) {
            $result[$field] = match ($field) {
                'found' => (bool) ($decoded['found'] ?? false),
                'confidence' => max(0.0, min(1.0, (float) ($decoded['confidence'] ?? 0))),
                default => trim((string) ($decoded[$field] ?? '')),
            };
        }

        return $result;
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) config('openrouter.api_key'))
            ->withHeaders(array_filter([
                'HTTP-Referer' => (string) config('openrouter.referer'),
                'X-OpenRouter-Title' => (string) config('openrouter.title'),
            ], 'filled'))
            ->baseUrl(rtrim((string) (config('openrouter.base_uri') ?: 'https://openrouter.ai/api/v1'), '/'))
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $transcript): array
    {
        $text = fn (string $description) => ['type' => 'string', 'description' => $description];

        return [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => "Conversation (oldest first):\n\n".$transcript],
            ],
            // Extraction, not writing: the same chat should give the same answer.
            'temperature' => 0,
            'max_tokens' => 600,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'order_delivery_address',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => self::FIELDS,
                        'properties' => [
                            'found' => ['type' => 'boolean', 'description' => 'Whether the customer gave a delivery address at all.'],
                            'address_text' => $text('The customer\'s address exactly as they typed it, copied verbatim. Join lines with ", " if it spans several messages.'),
                            'street' => $text('House/unit/lot number, street, purok, sitio, subdivision or village. Empty if not given.'),
                            'barangay' => $text('Barangay name only, without the word "Barangay"/"Brgy". Empty if not given.'),
                            'city' => $text('City or municipality name. Empty if not given.'),
                            'province' => $text('Province. Use "Metro Manila" for NCR cities. Empty if it cannot be told.'),
                            'landmark' => $text('Landmark or delivery note (near church, beside store…). Empty if none.'),
                            'confidence' => ['type' => 'number', 'description' => '0 to 1: how sure you are this is the correct, complete delivery address.'],
                        ],
                    ],
                ],
            ],
            // Only route to endpoints that honour strict structured outputs.
            'provider' => ['require_parameters' => true],
        ];
    }

    private function systemPrompt(): string
    {
        return implode(' ', [
            'You read Facebook Messenger chats between a Philippine online shop ("Page") and a customer ("Customer")',
            'and pull out the delivery address for a cash-on-delivery order. Messages are often in Taglish, Bisaya or',
            'abbreviated ("brgy", "sta.", "purok", "blk", "lt").',
            'Use the address the customer wants the parcel delivered to. If they gave more than one, use the latest one;',
            'if the Page repeated an address back and the customer agreed, that counts.',
            'Never invent a part they did not give. You may fill the province when the city makes it obvious',
            '(e.g. "Quezon City" → Metro Manila, "Lipa City" → Batangas), but leave it empty when the city name is shared',
            'by several provinces and nothing else in the chat tells them apart.',
            'Ignore the shop\'s own address, names, phone numbers, product talk and payment details.',
            'If no delivery address was given, set found to false, every text field to "" and confidence to 0.',
        ]);
    }
}
