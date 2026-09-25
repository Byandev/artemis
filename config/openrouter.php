<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenRouter API Key and Base URL
    |--------------------------------------------------------------------------
    |
    | The RDP Builder's name suggestions and packshots both go through
    | OpenRouter — plain OpenAI-compatible HTTP, same bearer auth, same
    | /chat/completions and /images shapes, with namespaced model slugs.
    |
    | Kept apart from config/openai.php, which the Ask widgets still read.
    */

    'api_key' => env('OPEN_ROUTER_API_KEY'),

    'base_uri' => env('OPEN_ROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),

    'request_timeout' => env('OPEN_ROUTER_REQUEST_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Attribution
    |--------------------------------------------------------------------------
    |
    | The headers OpenRouter reads for its leaderboards. Both optional, and
    | sent only when set.
    */

    'referer' => env('OPEN_ROUTER_HTTP_REFERER'),

    'title' => env('OPEN_ROUTER_APP_TITLE'),

    /*
    |--------------------------------------------------------------------------
    | RDP Builder Model
    |--------------------------------------------------------------------------
    |
    | The model behind the RDP Builder's "Suggest 10 names" step. Naming plus a
    | one-line rationale is an easy generation task, so the cheap tier is the
    | right default — and every press of that button is a paid call.
    |
    | Whatever this points at has to support strict structured outputs
    | (response_format: json_schema): the grid needs exactly ten
    | {name, rationale} pairs, and Modules\Products\Services\ProductResearchNameSuggester
    | pins that with a schema rather than parsing prose. The suggester also
    | asks OpenRouter to route only to endpoints that honour every parameter
    | sent, since one model is served by several and not all of them do.
    |
    */

    'product_research_model' => env('OPEN_ROUTER_PRODUCT_RESEARCH_MODEL', 'openai/gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | RDP Packshot Model
    |--------------------------------------------------------------------------
    |
    | The model behind Step 3's "Generate N options". This hits the images
    | endpoint, not chat completions, so it needs a model with image output.
    |
    | Images cost orders of magnitude more than the naming step and are far
    | slower — several at once routinely runs past a minute — hence the
    | separate timeout.
    |
    */

    // Compared head to head on the same brief: seedream returned a genuine
    // studio photograph — real spray mist, grit on the surface, honest depth
    // of field — where gpt-image-2 and flux both returned something closer to
    // a clean mockup. It was also seven times faster (12s against 84s), which
    // matters because this request is synchronous.
    'packshot_model' => env('OPEN_ROUTER_PACKSHOT_MODEL', 'bytedance-seed/seedream-4.5'),

    'packshot_timeout' => env('OPEN_ROUTER_PACKSHOT_TIMEOUT', 180),

    /*
    | Quality and framing for the images endpoint. Portrait by default: a
    | bottle, tube or pouch is taller than it is wide, and a square crop wastes
    | half the frame on background.
    */

    'packshot_quality' => env('OPEN_ROUTER_PACKSHOT_QUALITY', 'high'),

    'packshot_aspect_ratio' => env('OPEN_ROUTER_PACKSHOT_ASPECT_RATIO', '3:4'),
];
