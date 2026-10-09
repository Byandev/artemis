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
     * @return array{found: bool, address_text: string, street: string, barangay: string, city: string, province: string, landmark: string, confidence: float, usage: array{cost_usd: ?float, input_tokens: ?int, output_tokens: ?int}}
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

        [$decoded, $usage] = $this->complete($this->payload($transcript));

        $result = [];

        foreach (self::FIELDS as $field) {
            $result[$field] = match ($field) {
                'found' => (bool) ($decoded['found'] ?? false),
                'confidence' => max(0.0, min(1.0, (float) ($decoded['confidence'] ?? 0))),
                default => trim((string) ($decoded[$field] ?? '')),
            };
        }

        $result['usage'] = $usage;

        return $result;
    }

    /**
     * The second check: when what the customer typed matched no Pancake name,
     * ask which of the real options they meant. The answer is limited to the
     * ids given (or none), so the model can only pick, never invent.
     *
     * Never throws — a failed pick just leaves the level unmatched.
     *
     * @param  'province'|'city or municipality'|'barangay'  $level
     * @param  array<string, string>  $options  id => name
     * @return array{id: ?string, usage: array{cost_usd: ?float, input_tokens: ?int, output_tokens: ?int}}
     */
    public function pick(string $level, string $typed, string $addressText, array $options): array
    {
        $none = ['id' => null, 'usage' => $this->usage([])];

        if (! self::isConfigured() || $options === [] || trim($typed) === '') {
            return $none;
        }

        $list = collect($options)->map(fn (string $name, string $id) => "{$id}: {$name}")->implode("\n");

        try {
            [$decoded, $usage] = $this->complete([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => implode(' ', [
                        "A Philippine customer typed a {$level} name that is misspelled, abbreviated, a nickname or a local name.",
                        'Pick the official place from the list that they mean. Use the full address for context.',
                        'Only pick when you are confident; otherwise answer with an empty id.',
                    ])],
                    ['role' => 'user', 'content' => "Typed {$level}: {$typed}\nFull address: {$addressText}\n\nOptions (id: name):\n{$list}"],
                ],
                'temperature' => 0,
                'max_tokens' => 60,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'pick_place',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['id'],
                            'properties' => [
                                'id' => ['type' => 'string', 'enum' => [...array_map('strval', array_keys($options)), '']],
                            ],
                        ],
                    ],
                ],
                'provider' => ['require_parameters' => true],
                'usage' => ['include' => true],
            ]);
        } catch (AddressExtractionFailed) {
            return $none;
        }

        $id = (string) ($decoded['id'] ?? '');

        return ['id' => array_key_exists($id, $options) ? $id : null, 'usage' => $usage];
    }

    /**
     * Send one chat completion and decode its JSON answer.
     *
     * @return array{0: array, 1: array{cost_usd: ?float, input_tokens: ?int, output_tokens: ?int}}
     *
     * @throws AddressExtractionFailed
     */
    private function complete(array $payload): array
    {
        try {
            $response = $this->request()->post('/chat/completions', $payload);
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

        return [$decoded, $this->usage((array) data_get($response->json(), 'usage', []))];
    }

    /**
     * What the call cost, as OpenRouter reported it — `cost` is in US dollars
     * (OpenRouter credits). Each part is null when the provider left it out.
     *
     * @return array{cost_usd: ?float, input_tokens: ?int, output_tokens: ?int}
     */
    public function usage(array $usage): array
    {
        return [
            'cost_usd' => isset($usage['cost']) ? (float) $usage['cost'] : null,
            'input_tokens' => isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            'output_tokens' => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
        ];
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
            // Have OpenRouter put the call's cost in the answer's `usage`.
            'usage' => ['include' => true],
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
