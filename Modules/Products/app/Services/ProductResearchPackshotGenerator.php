<?php

namespace Modules\Products\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Products\Exceptions\ProductResearchSuggestionFailed;

/**
 * Draws packshot options for an RDP.
 *
 * Whether the render carries branding is decided by the workspace's image
 * prompt, whose default asks for "a printed label carrying the name". This
 * only adds the parts the prompt cannot know: what the form physically is,
 * what palette the market wears, and — since lettering is where these models
 * still slip — that the name must be spelled exactly right wherever it appears.
 *
 * What comes back is a concept for the lab, not artwork to print.
 *
 * Uses OpenRouter's OpenAI-compatible images endpoint, which returns base64
 * rather than a URL — so the bytes are handed straight to media-library and
 * never fetched a second time.
 */
class ProductResearchPackshotGenerator
{
    /**
     * A palette cue per target market.
     *
     * Left to itself the model reaches for amber glass and brown pouches every
     * time, whatever the product treats — so the category, which is the one
     * thing a buyer scans a shelf for, sets the colour. These follow the usual
     * health-category conventions rather than anything brand-specific; a
     * workspace that wants its own look overrides it in the style prompt.
     *
     * @var array<string, string>
     */
    private const MARKET_PALETTES = [
        'cardiovascular' => 'deep red and crimson',
        'metabolic & endocrine' => 'fresh green and teal',
        'respiratory' => 'cool sky blue and white',
        'infectious diseases' => 'clean clinical blue and white',
        'gastrointestinal' => 'soft mint and cream',
        'neurological' => 'violet and deep indigo',
        'musculoskeletal' => 'warm orange and charcoal',
        'oncology' => 'lilac and soft purple',
        "women's health" => 'rose and blush pink',
        "men's health" => 'slate blue and navy',
    ];

    private string $model;

    private string $quality;

    private string $aspectRatio;

    private int $timeout;

    public function __construct()
    {
        $this->model = (string) config('openrouter.packshot_model', 'openai/gpt-image-2');
        $this->quality = (string) config('openrouter.packshot_quality', 'high');
        $this->aspectRatio = (string) config('openrouter.packshot_aspect_ratio', '3:4');
        // Images are far slower than text: several at once routinely runs past
        // a minute, where the naming call settles in under twenty seconds.
        $this->timeout = (int) config('openrouter.packshot_timeout', 180);
    }

    public static function isConfigured(): bool
    {
        return filled(config('openrouter.api_key'));
    }

    /**
     * @return list<array{data: string, mime: string}> raw image bytes, decoded
     *
     * @throws ProductResearchSuggestionFailed
     */
    public function generate(
        string $name,
        string $form,
        string $formDescription,
        string $market,
        ?string $subCategory,
        string $stylePrompt,
        int $count,
    ): array {
        $prompt = $this->prompt($name, $form, $formDescription, $market, $subCategory, $stylePrompt);

        // One request per option rather than `n => $count`. Support for `n` is
        // not portable — seedream ignores it and answers with a single image
        // whatever is asked for, where gpt-image-2 honours it — so asking once
        // per image is the only thing that behaves the same everywhere.
        //
        // Pooled, so the wall time is roughly one image rather than $count of
        // them, and separately seeded, so the set does not come back as the
        // same picture repeated.
        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn () => $this->pooled($pool)->post('/images', [
                'model' => $this->model,
                'prompt' => $prompt,
                'n' => 1,
                'quality' => $this->quality,
                'aspect_ratio' => $this->aspectRatio,
                'seed' => random_int(1, 2_000_000_000),
            ]),
            range(1, $count),
        ));

        $images = [];
        $failure = null;

        foreach ($responses as $response) {
            // A pooled request hands back the exception rather than throwing.
            if ($response instanceof ConnectionException) {
                Log::warning('Product research packshots: could not reach the provider.', [
                    'reason' => $response->getMessage(),
                ]);
                $failure ??= ProductResearchSuggestionFailed::unreachable();

                continue;
            }

            if (! $response instanceof Response) {
                $failure ??= ProductResearchSuggestionFailed::unusableAnswer();

                continue;
            }

            if (! $response->successful()) {
                Log::warning('Product research packshots: the provider refused the request.', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
                $failure ??= ProductResearchSuggestionFailed::upstream($response->status());

                continue;
            }

            // Still read as a list: a model that does honour `n` may answer a
            // single request with more than one image.
            foreach ($this->decode((array) $response->json()) as $image) {
                $images[] = $image;
            }
        }

        if ($images === []) {
            throw $failure ?? ProductResearchSuggestionFailed::unusableAnswer();
        }

        // Some back is better than none — the grid wraps, and a partial set is
        // still something to choose from.
        return array_slice($images, 0, $count);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array{data: string, mime: string}>
     */
    private function decode(array $body): array
    {
        $images = [];

        foreach ((array) data_get($body, 'data', []) as $item) {
            $encoded = data_get($item, 'b64_json');

            if (! is_string($encoded) || $encoded === '') {
                continue;
            }

            $decoded = base64_decode($encoded, true);

            if ($decoded === false || $decoded === '') {
                continue;
            }

            $images[] = [
                'data' => $decoded,
                'mime' => (string) (data_get($item, 'media_type') ?: 'image/png'),
            ];
        }

        if ($images === []) {
            Log::warning('Product research packshots: an answer carried no usable image.');
        }

        return $images;
    }

    private function pooled(Pool $pool): PendingRequest
    {
        return $pool->withToken((string) config('openrouter.api_key'))
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
     * The workspace's style note, then the subject. The no-lettering rule is
     * appended rather than left editable: without it the model writes a mangled
     * brand name onto the container every time.
     */
    private function prompt(
        string $name,
        string $form,
        string $formDescription,
        string $market,
        ?string $subCategory,
        string $stylePrompt,
    ): string {
        $treats = filled($subCategory) ? "{$subCategory} within {$market}" : $market;

        return implode(' ', [
            trim($stylePrompt),
            "The subject is {$formDescription}, a {$form} product for {$treats}.",
            'Show only that product, centred, one item per image, and nothing else in frame.',
            // Said twice, in effect: these models reach for a bottle whatever
            // they are told, so the packaging is restated as non-negotiable.
            "The packaging must be exactly {$formDescription} and nothing else —",
            'do not substitute a bottle, jar or any other container.',
            // Said explicitly because the models drift to CGI otherwise: a
            // rendered-looking bottle is the default unless photography is
            // asked for by name.
            'This is a real photograph taken in a studio, not a 3D render, illustration or digital mockup.',
            'Real glass and plastic with true specular highlights, natural depth of field,',
            'honest material texture, and the faint imperfections of an actual product on a real surface.',
            "Colour the packaging in {$this->paletteFor($market)}, and give each image a different",
            'shade or finish from that palette so the set does not repeat itself.',
            // Conditional on purpose: the prompt above decides whether there is
            // a label at all, and this has to stay harmless when there is not.
            "Wherever the packaging carries wording, the product name reads exactly \"{$name}\",",
            'correctly spelled and properly kerned, and no other words appear anywhere on it.',
        ]);
    }

    /**
     * The palette for a market, or a neutral one for a market this does not
     * know about — never the model's own default, which is always brown.
     */
    private function paletteFor(string $market): string
    {
        $key = mb_strtolower(trim($market));

        return self::MARKET_PALETTES[$key] ?? 'clean modern colours suited to the condition it treats';
    }
}
