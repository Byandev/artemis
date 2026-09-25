<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key and Organization
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API Key and organization. This will be
    | used to authenticate with the OpenAI API - you can find your API key
    | and organization on your OpenAI dashboard, at https://openai.com.
    */

    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Project
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API project. This is used optionally in
    | situations where you are using a legacy user API key and need association
    | with a project. This is not required for the newer API keys.
    */
    'project' => env('OPENAI_PROJECT'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Base URL
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API base URL used to make requests. This
    | is needed if using a custom API endpoint. Defaults to: api.openai.com/v1
    */
    'base_uri' => env('OPENAI_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout may be used to specify the maximum number of seconds to wait
    | for a response. By default, the client will time out after 30 seconds.
    */

    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),

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
    | pins that with a schema rather than parsing prose.
    |
    */

    'product_research_model' => env('OPENAI_PRODUCT_RESEARCH_MODEL', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | Gateway Options
    |--------------------------------------------------------------------------
    |
    | The client here is plain OpenAI-compatible HTTP, so pointing
    | OPENAI_BASE_URL at a gateway such as OpenRouter
    | (https://openrouter.ai/api/v1) works without a code change. These two
    | settings cover the differences that are not just the URL.
    |
    | `referer` / `title` are the attribution headers OpenRouter reads for its
    | leaderboards. Both are optional and ignored by OpenAI itself.
    |
    | `require_provider_parameters` matters more than it looks. OpenRouter
    | serves one model through several provider endpoints and only some of them
    | support strict structured outputs; routed to one that does not, the
    | request fails outright rather than degrading — and ProductResearchNameSuggester
    | depends on that schema. Turning this on tells OpenRouter to only route to
    | endpoints that support every parameter sent. It is off by default because
    | OpenAI rejects the unknown `provider` key.
    |
    */

    'referer' => env('OPENAI_HTTP_REFERER'),

    'title' => env('OPENAI_APP_TITLE'),

    'require_provider_parameters' => env('OPENAI_REQUIRE_PROVIDER_PARAMETERS', false),

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
    'packshot_model' => env('OPENAI_PACKSHOT_MODEL', 'bytedance-seed/seedream-4.5'),

    'packshot_timeout' => env('OPENAI_PACKSHOT_TIMEOUT', 180),

    /*
    | Quality and framing for the images endpoint. Portrait by default: a
    | bottle, tube or pouch is taller than it is wide, and a square crop wastes
    | half the frame on background.
    */

    'packshot_quality' => env('OPENAI_PACKSHOT_QUALITY', 'high'),

    'packshot_aspect_ratio' => env('OPENAI_PACKSHOT_ASPECT_RATIO', '3:4'),
];
