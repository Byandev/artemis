<?php

namespace Modules\Products\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Products\Exceptions\ProductResearchSuggestionFailed;

/**
 * Names a product for the RDP Builder's "Suggest 10 names" step.
 *
 * Given the brief the user has already picked — the delivery form, the target
 * market and its sub category — this asks the model for a positioning line and
 * ten candidate names, each with the reasoning behind it, which the builder
 * renders as the "Pick a name" grid.
 *
 * The shape of the answer is pinned with a strict JSON schema rather than asked
 * for in prose: the grid needs exactly ten `{name, rationale}` pairs, and
 * parsing that back out of free text would fail quietly and often.
 *
 * Talks to OpenAI over the HTTP client rather than through openai-php/laravel,
 * which this repo has never actually had installed — see config/openai.php and
 * the note in App\Http\Controllers\Workspaces\AskDataController.
 */
class ProductResearchNameSuggester
{
    private string $model;

    private int $timeout;

    public function __construct()
    {
        $this->model = (string) config('openai.product_research_model', 'gpt-4o-mini');
        $this->timeout = (int) config('openai.request_timeout', 30);
    }

    /**
     * Whether an API key is on file. Without one there is nothing to call, and
     * the controller says so rather than letting a request fail on the wire.
     */
    public static function isConfigured(): bool
    {
        return filled(config('openai.api_key'));
    }

    /**
     * @return array{positioning: string, names: list<array{name: string, rationale: string}>}
     *
     * @throws ProductResearchSuggestionFailed
     */
    public function suggest(
        string $form,
        string $market,
        ?string $subCategory,
        string $namingPrompt,
        int $count,
    ): array {
        $response = $this->send($this->payload($form, $market, $subCategory, $namingPrompt, $count));

        $content = data_get($response, 'choices.0.message.content');

        if (! is_string($content) || $content === '') {
            Log::warning('Product research name suggestions: no content in the answer.', [
                'finish_reason' => data_get($response, 'choices.0.finish_reason'),
            ]);

            throw ProductResearchSuggestionFailed::unusableAnswer();
        }

        return $this->parse($content, $count);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ProductResearchSuggestionFailed
     */
    private function send(array $payload): array
    {
        try {
            $response = $this->request()->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            // A timeout or a DNS failure. Logged with the message only — the
            // trace would carry the request body, and that carries the brief.
            Log::warning('Product research name suggestions: could not reach the provider.', [
                'reason' => $e->getMessage(),
            ]);

            throw ProductResearchSuggestionFailed::unreachable();
        }

        if (! $response->successful()) {
            // The body is deliberately kept out of the exception and truncated
            // here: a provider error can echo the prompt back.
            Log::warning('Product research name suggestions: the provider refused the request.', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw ProductResearchSuggestionFailed::upstream($response->status());
        }

        return (array) $response->json();
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) config('openai.api_key'))
            ->withHeaders($this->attributionHeaders())
            ->baseUrl(rtrim((string) (config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/'))
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson();
    }

    /**
     * Attribution headers OpenRouter reads for its leaderboards. Sent only when
     * configured, and ignored by OpenAI itself.
     *
     * @return array<string, string>
     */
    private function attributionHeaders(): array
    {
        return array_filter([
            'HTTP-Referer' => (string) config('openai.referer'),
            'X-OpenRouter-Title' => (string) config('openai.title'),
        ], 'filled');
    }

    /**
     * Turn the model's JSON string into the shape the builder renders.
     *
     * Checked rather than trusted even though the schema is strict: a refusal
     * or a truncated answer still arrives as a 200.
     *
     * @return array{positioning: string, names: list<array{name: string, rationale: string}>}
     *
     * @throws ProductResearchSuggestionFailed
     */
    private function parse(string $content, int $count): array
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            Log::warning('Product research name suggestions: the answer was not JSON.');

            throw ProductResearchSuggestionFailed::unusableAnswer();
        }

        $names = [];

        foreach ((array) data_get($decoded, 'names', []) as $candidate) {
            $name = trim((string) data_get($candidate, 'name', ''));
            $rationale = trim((string) data_get($candidate, 'rationale', ''));

            if ($name === '') {
                continue;
            }

            $names[] = ['name' => $name, 'rationale' => $rationale];
        }

        if ($names === []) {
            Log::warning('Product research name suggestions: the answer carried no usable names.');

            throw ProductResearchSuggestionFailed::unusableAnswer();
        }

        return [
            'positioning' => trim((string) data_get($decoded, 'positioning', '')),
            // Fewer than asked for is still worth showing — the grid wraps.
            // More than asked for is trimmed so the layout stays predictable.
            'names' => array_slice($names, 0, $count),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        string $form,
        string $market,
        ?string $subCategory,
        string $namingPrompt,
        int $count,
    ): array {
        $brief = "Product form: {$form}\nTarget market: {$market}";

        if (filled($subCategory)) {
            $brief .= "\nSub category: {$subCategory}";
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt($namingPrompt, $count)],
                ['role' => 'user', 'content' => $brief],
            ],
            // Naming wants variety, where AskDataController's analysis wants
            // the opposite and sits at 0.5.
            'temperature' => 0.8,
            // Roughly 80 tokens per name and its reasoning, plus the
            // positioning line and some slack — a fixed ceiling would truncate
            // the answer once the count is raised.
            'max_tokens' => 200 + ($count * 80),
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'product_research_name_suggestions',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['positioning', 'names'],
                        'properties' => [
                            'positioning' => [
                                'type' => 'string',
                                'description' => 'One sentence on what the product promises the buyer.',
                            ],
                            'names' => [
                                'type' => 'array',
                                'minItems' => $count,
                                'maxItems' => $count,
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'required' => ['name', 'rationale'],
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'rationale' => [
                                            'type' => 'string',
                                            'description' => 'One sentence on the angle the name takes.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // OpenRouter serves one model through several provider endpoints and
        // only some support strict structured outputs; routed to one that does
        // not, the request fails outright. This pins routing to endpoints that
        // honour every parameter above. OpenAI rejects the unknown key, so it
        // stays off unless the gateway needs it.
        if (config('openai.require_provider_parameters')) {
            $payload['provider'] = ['require_parameters' => true];
        }

        return $payload;
    }

    /**
     * The brand's half of the prompt, then the rules that keep the answer
     * usable.
     *
     * The first part is whatever the workspace saved in the Configure prompt
     * dialog. Everything after it is fixed: the grid needs one sentence of
     * reasoning per name, and the two rules at the end were written against
     * real output — the first run named a "Cream" for a balm brief, and a later
     * one returned ten wordings of "soothing relief".
     */
    private function systemPrompt(string $namingPrompt, int $count): string
    {
        return implode(' ', [
            trim($namingPrompt),
            "Write one positioning sentence and {$count} candidate product names.",
            'The positioning sentence is the promise to the buyer, in their words, not a description',
            'of the product — say what they get back, not what the product is.',
            'A name is a brand, not a sentence or an instruction to the reader.',
            'Never name a delivery form other than the one in the brief, and do not repeat that form in every name.',
            'Each name gets one sentence explaining the angle it takes — the benefit it leads with or who it speaks to.',
            'Vary the angles hard across the set: relief, speed, mobility, protection, routine, strength, calm.',
            'Do not restate one idea ten ways, and do not open more than two names with the same word.',
        ]);
    }
}
