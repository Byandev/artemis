<?php

namespace Modules\Products\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Products\Exceptions\ProductResearchSuggestionFailed;
use Modules\Products\Models\ProductForm;
use Modules\Products\Models\ProductResearch;
use Modules\Products\Models\TargetMarket;
use Modules\Products\Services\ProductResearchNameSuggester;
use Modules\Products\Services\ProductResearchPackshotGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductResearchController extends Controller
{
    use AuthorizesRequests;

    /** Handled by fillPrompts() rather than plain fill(), so they normalise. */
    private const PROMPT_FIELDS = ['naming_prompt', 'name_count', 'packshot_prompt', 'packshot_count'];

    /** The RDPs list. */
    public function index(Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ViewProductResearch->value, $workspace);

        $productResearches = ProductResearch::ofWorkspace($workspace)
            ->with(['form:id,name', 'targetMarket:id,name', 'targetMarketSub:id,name', 'creator:id,name'])
            ->latest()
            ->get()
            ->map(fn (ProductResearch $productResearch) => [
                'id' => $productResearch->id,
                'name' => $productResearch->name,
                'form' => $productResearch->form?->name,
                // The list shows the sub category — "Back Pain", not
                // "Musculoskeletal" — falling back to the category when the
                // brief was filed without one.
                'target_market' => $productResearch->targetMarketSub?->name ?? $productResearch->targetMarket?->name,
                'date' => $productResearch->created_at?->toDateString(),
                'created_by' => $productResearch->creator?->name,
            ]);

        return Inertia::render('workspaces/products/product-research/index', [
            'workspace' => $workspace,
            'productResearches' => $productResearches,
        ]);
    }

    /** The builder, on a brief that does not exist yet. */
    public function create(Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageProductResearch->value, $workspace);

        return Inertia::render('workspaces/products/product-research/builder', [
            'workspace' => $workspace,
            'productResearch' => null,
            // A brief that does not exist yet opens on the defaults.
            'promptSettings' => $this->promptSettings(null),
            ...$this->options($workspace),
        ]);
    }

    /** The builder, on a brief already filed. */
    public function edit(Workspace $workspace, ProductResearch $productResearch)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ViewProductResearch->value, $workspace);
        $this->guard($workspace, $productResearch);

        return Inertia::render('workspaces/products/product-research/builder', [
            'workspace' => $workspace,
            'productResearch' => [
                'id' => $productResearch->id,
                'product_form_id' => $productResearch->product_form_id,
                'target_market_id' => $productResearch->target_market_id,
                'target_market_sub_id' => $productResearch->target_market_sub_id,
                'name' => $productResearch->name,
                'positioning' => $productResearch->positioning,
                'claims' => $productResearch->claims,
                'active_ingredients' => $productResearch->active_ingredients,
                'additional_instruction' => $productResearch->additional_instruction,
                ...$this->packshotPayload($workspace, $productResearch),
            ],
            'promptSettings' => $this->promptSettings($productResearch),
            ...$this->options($workspace),
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageProductResearch->value, $workspace);

        $productResearch = $this->fileBrief(
            $request->validate($this->rules($request, $workspace)),
            $workspace,
            $request,
        );

        return redirect()
            ->route('workspaces.products.product-research.index', $workspace)
            ->with('success', "\"{$productResearch->name}\" saved to RDPs.");
    }

    public function update(Request $request, Workspace $workspace, ProductResearch $productResearch)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageProductResearch->value, $workspace);
        $this->guard($workspace, $productResearch);

        $validated = $request->validate($this->rules($request, $workspace));

        $this->fillPrompts($productResearch, $validated)
            ->fill(Arr::except($validated, self::PROMPT_FIELDS))
            ->save();

        return redirect()
            ->route('workspaces.products.product-research.index', $workspace)
            ->with('success', "\"{$productResearch->name}\" updated.");
    }

    /**
     * Candidate names and a positioning line for the brief so far — as many as
     * the workspace asked for in the Configure prompt dialog.
     *
     * Gated on ManageProductResearch rather than ViewProductResearch: every press of the
     * button is a paid call, so someone who may only read the list must not be
     * able to spend against the workspace's account.
     */
    public function suggestNames(Request $request, Workspace $workspace, ProductResearchNameSuggester $suggester)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageProductResearch->value, $workspace);

        $validated = $request->validate([
            ...$this->briefRules($request, $workspace),
            ...$this->promptRules(),
        ]);

        if (! ProductResearchNameSuggester::isConfigured()) {
            return response()->json([
                'message' => "Name suggestions aren't configured yet. Set OPENAI_API_KEY to switch this on.",
            ], 503);
        }

        // Resolved to names here so nothing but the taxonomy's own wording ever
        // reaches the prompt — no ids, no workspace details.
        $form = ProductForm::ofWorkspace($workspace)->findOrFail($validated['product_form_id']);
        $market = TargetMarket::ofWorkspace($workspace)->findOrFail($validated['target_market_id']);
        $sub = filled($validated['target_market_sub_id'] ?? null)
            ? TargetMarket::ofWorkspace($workspace)->find($validated['target_market_sub_id'])
            : null;

        // The prompt rides on the request rather than being read back off a
        // stored row: Step 2 is where the name is chosen, so more often than
        // not there is no brief yet to read from. The builder holds the pair
        // and sends it; saving the brief is what persists it.
        $settings = $this->fillPrompts(new ProductResearch, $validated);

        try {
            return response()->json(
                $suggester->suggest(
                    $form->name,
                    $market->name,
                    $sub?->name,
                    $settings->prompt(),
                    $settings->count(),
                )
            );
        } catch (ProductResearchSuggestionFailed $e) {
            // The exception's message is written for the person who pressed the
            // button; the provider's own words stayed in the log.
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    /**
     * Draw packshot options for a saved brief.
     *
     * Only for a saved one: the options are files, and files need an owner. The
     * panel says so rather than silently disabling itself.
     */
    public function generatePackshots(Request $request, Workspace $workspace, ProductResearchPackshotGenerator $generator)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageProductResearch->value, $workspace);

        $productResearch = $this->resolveProductResearch($request, $workspace);

        if (! ProductResearchPackshotGenerator::isConfigured()) {
            return response()->json([
                'message' => "Packshot generation isn't configured yet. Set OPENAI_API_KEY to switch this on.",
            ], 503);
        }

        // Drawing several images routinely runs past a minute, and the web
        // SAPI caps execution at 30 seconds by default — the HTTP client's own
        // timeout never gets a chance to apply. Raised for this request only,
        // with headroom over the client timeout so the client is what gives up
        // first and the failure arrives as a readable message.
        set_time_limit((int) config('openai.packshot_timeout', 180) + 30);

        $productResearch->loadMissing(['form', 'targetMarket', 'targetMarketSub']);

        // Drawn with what the dialog is showing, and remembered on the brief,
        // so reopening it later shows what these images were drawn with.
        $this->fillPrompts($productResearch, $request->validate($this->promptRules()))->save();

        try {
            $images = $generator->generate(
                $productResearch->name,
                $productResearch->form?->name ?? 'product',
                // The form says how it is packaged; the generator no longer
                // guesses from the name.
                $productResearch->form?->packshotDescription() ?? 'the product in its retail packaging',
                // Category and sub category stay separate: the category picks
                // the palette, the sub category says what it treats.
                $productResearch->targetMarket?->name ?? 'general wellness',
                $productResearch->targetMarketSub?->name,
                $productResearch->packshotPrompt(),
                $productResearch->packshotCount(),
            );
        } catch (ProductResearchSuggestionFailed $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        // A fresh run replaces the previous options rather than piling up: the
        // grid shows one set, and the old files would otherwise sit in the
        // bucket unreachable.
        $productResearch->clearMediaCollection(ProductResearch::PACKSHOT_OPTIONS_COLLECTION);

        foreach ($images as $index => $image) {
            $productResearch->addMediaFromString($image['data'])
                ->usingFileName(Str::slug($productResearch->name ?: 'packshot')."-{$index}.".$this->extensionFor($image['mime']))
                ->toMediaCollection(ProductResearch::PACKSHOT_OPTIONS_COLLECTION);
        }

        return response()->json($this->packshotPayload($workspace, $productResearch->refresh()));
    }

    /** Someone's own render, used instead of a generated one. */
    public function uploadPackshot(Request $request, Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageProductResearch->value, $workspace);

        $request->validate([
            'packshot' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,svg', 'max:10240'],
        ]);

        $productResearch = $this->resolveProductResearch($request, $workspace);

        // singleFile(), so this replaces whatever was there.
        $productResearch->addMediaFromRequest('packshot')->toMediaCollection(ProductResearch::PACKSHOT_COLLECTION);

        return response()->json($this->packshotPayload($workspace, $productResearch->refresh()));
    }

    /** Promote one of the generated options to be the brief's packshot. */
    public function selectPackshot(Request $request, Workspace $workspace, ProductResearch $productResearch)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageProductResearch->value, $workspace);
        $this->guard($workspace, $productResearch);

        $validated = $request->validate(['media_id' => ['required', 'integer']]);

        // Looked up through the brief's own options rather than by id alone, so
        // another brief's file cannot be adopted.
        $option = $productResearch->getMedia(ProductResearch::PACKSHOT_OPTIONS_COLLECTION)
            ->firstWhere('id', $validated['media_id']);

        abort_unless($option !== null, 404);

        // Copied rather than moved: the option stays in the grid so a different
        // one can be picked afterwards.
        $option->copy($productResearch, ProductResearch::PACKSHOT_COLLECTION, $option->disk);

        return response()->json($this->packshotPayload($workspace, $productResearch->refresh()));
    }

    /**
     * The brief these packshots belong to, filing it first if it is still a
     * draft.
     *
     * A packshot is a file and a file needs an owner, so this used to refuse
     * until somebody pressed Save to RDPs. Everything needed to file the brief
     * — a name, a form, a market — is on screen by the time Step 3 appears, so
     * there is nothing to wait for: the draft is saved on the way through and
     * the builder switches to editing it.
     */
    private function resolveProductResearch(Request $request, Workspace $workspace): ProductResearch
    {
        if ($request->filled('product_research_id')) {
            $productResearch = ProductResearch::find($request->integer('product_research_id'));
            $this->guard($workspace, $productResearch ?? new ProductResearch);

            return $productResearch;
        }

        return $this->fileBrief(
            $request->validate($this->rules($request, $workspace)),
            $workspace,
            $request,
        );
    }

    /**
     * Serve a packshot or one of its options. The bucket is private, so this
     * hands out a short-lived signed URL, or streams the bytes when the disk
     * cannot sign one.
     */
    public function showPackshotImage(Workspace $workspace, ProductResearch $productResearch, Media $media)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ViewProductResearch->value, $workspace);
        $this->guard($workspace, $productResearch);

        abort_unless(
            $media->model_type === $productResearch->getMorphClass() && $media->model_id === $productResearch->getKey(),
            404,
        );

        $disk = Storage::disk($media->disk);

        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl(
                $media->getPathRelativeToRoot(),
                Carbon::now()->addMinutes(5),
            ));
        }

        return $disk->download($media->getPathRelativeToRoot(), $media->file_name);
    }

    /**
     * The chosen packshot and the options behind it, as the panel renders them.
     *
     * @return array<string, mixed>
     */
    private function packshotPayload(Workspace $workspace, ProductResearch $productResearch): array
    {
        $url = fn (Media $media) => route('workspaces.products.product-research.packshot-image', [
            'workspace' => $workspace,
            'productResearch' => $productResearch->id,
            'media' => $media->id,
        ]);

        $packshot = $productResearch->getFirstMedia(ProductResearch::PACKSHOT_COLLECTION);

        return [
            // Handed back so a builder that was still a draft knows which
            // brief it is now editing.
            'product_research_id' => $productResearch->id,
            'packshot' => $packshot ? ['id' => $packshot->id, 'url' => $url($packshot)] : null,
            'packshot_options' => $productResearch->getMedia(ProductResearch::PACKSHOT_OPTIONS_COLLECTION)
                ->map(fn (Media $media) => ['id' => $media->id, 'url' => $url($media)])
                ->values()
                ->all(),
        ];
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'png',
        };
    }

    /**
     * What the Configure prompt dialog opens on, for one brief.
     *
     * A brief that does not exist yet — the builder on /create — opens on the
     * defaults, which is what an unsaved model already reports. `is_default`
     * drives whether "Reset to default" has anything to undo.
     *
     * @return array<string, mixed>
     */
    private function promptSettings(?ProductResearch $productResearch): array
    {
        $productResearch ??= new ProductResearch;

        return [
            'naming_prompt' => $productResearch->prompt(),
            'name_count' => $productResearch->count(),
            'default_prompt' => ProductResearch::DEFAULT_PROMPT,
            'max_count' => ProductResearch::MAX_COUNT,
            'is_default' => blank($productResearch->naming_prompt),
            'packshot_prompt' => $productResearch->packshotPrompt(),
            'packshot_count' => $productResearch->packshotCount(),
            'default_packshot_prompt' => ProductResearch::DEFAULT_PACKSHOT_PROMPT,
            'max_packshot_count' => ProductResearch::MAX_PACKSHOT_COUNT,
            'packshot_is_default' => blank($productResearch->packshot_prompt),
        ];
    }

    /**
     * Create a brief from validated input.
     *
     * Shared by store() and the draft that Step 3 files on the way through, so
     * both normalise the prompts the same way rather than mass-assigning the
     * default's text verbatim.
     *
     * @param  array<string, mixed>  $validated
     */
    private function fileBrief(array $validated, Workspace $workspace, Request $request): ProductResearch
    {
        $productResearch = $this->fillPrompts(new ProductResearch, $validated)
            ->fill(Arr::except($validated, self::PROMPT_FIELDS));

        $productResearch->workspace_id = $workspace->id;
        $productResearch->created_by = $request->user()->id;
        $productResearch->save();

        return $productResearch;
    }

    /**
     * The four prompt fields, as every endpoint that touches them accepts them.
     *
     * `sometimes` throughout: a caller changing only the naming pair must not
     * have to restate the packshot one, and must not have it wiped for leaving
     * it out. Null is "reset to default" — the model falls back rather than
     * storing a copy of the default that would then never track it.
     *
     * @return array<string, array<int, mixed>>
     */
    private function promptRules(): array
    {
        return [
            'naming_prompt' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'name_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.ProductResearch::MAX_COUNT],
            'packshot_prompt' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'packshot_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.ProductResearch::MAX_PACKSHOT_COUNT],
        ];
    }

    /**
     * Put the validated prompt fields on a brief, saved or not.
     *
     * Storing the default verbatim would freeze the brief on today's wording,
     * so it is normalised back to null on the way in.
     *
     * @param  array<string, mixed>  $validated
     */
    private function fillPrompts(ProductResearch $productResearch, array $validated): ProductResearch
    {
        $normalise = function (?string $value, string $default): ?string {
            $value = trim((string) $value);

            return ($value === '' || $value === $default) ? null : $value;
        };

        if (array_key_exists('naming_prompt', $validated)) {
            $productResearch->naming_prompt = $normalise($validated['naming_prompt'], ProductResearch::DEFAULT_PROMPT);
        }

        if (array_key_exists('packshot_prompt', $validated)) {
            $productResearch->packshot_prompt = $normalise($validated['packshot_prompt'], ProductResearch::DEFAULT_PACKSHOT_PROMPT);
        }

        // Blank is "leave it as it was" rather than zero, which the counts can
        // never be — the stepper's floor is 1.
        foreach (['name_count', 'packshot_count'] as $key) {
            if (filled($validated[$key] ?? null)) {
                $productResearch->{$key} = $validated[$key];
            }
        }

        return $productResearch;
    }

    /**
     * Workspace owners hold '*', so the permission checks above wave them
     * through whether or not the workspace bought the module.
     */
    private function guardModule(Workspace $workspace): void
    {
        abort_unless($workspace->products_module_enabled, 404);
    }

    /**
     * Route-model binding resolves a brief by id alone, so a member of one
     * workspace could otherwise open another workspace's.
     */
    private function guard(Workspace $workspace, ?ProductResearch $productResearch): void
    {
        abort_unless($productResearch !== null && $productResearch->workspace_id === $workspace->id, 404);
    }

    /**
     * What the Brief step's three pickers offer. The target markets carry
     * their sub categories so picking a category can narrow the third select
     * without another round trip.
     *
     * @return array<string, mixed>
     */
    private function options(Workspace $workspace): array
    {
        return [
            'forms' => ProductForm::ofWorkspace($workspace)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'targetMarkets' => TargetMarket::ofWorkspace($workspace)
                ->categories()
                ->with(['children' => fn ($q) => $q->select('id', 'parent_id', 'name', 'position')->ordered()])
                ->select('id', 'name', 'position')
                ->ordered()
                ->get(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(Request $request, Workspace $workspace): array
    {
        return [
            // Required: a brief the lab cannot file under a name is not a
            // brief, and the list has a column for it.
            'name' => ['required', 'string', 'max:255'],
            ...$this->briefRules($request, $workspace),
            ...$this->promptRules(),
            'positioning' => ['nullable', 'string'],
            'claims' => ['nullable', 'string'],
            'active_ingredients' => ['nullable', 'string'],
            'additional_instruction' => ['nullable', 'string'],
        ];
    }

    /**
     * The three pickers in the Brief step. Shared with suggestNames() so the
     * generator can't be handed a pairing that saving would reject.
     *
     * @return array<string, array<int, mixed>>
     */
    private function briefRules(Request $request, Workspace $workspace): array
    {
        $categoryId = $request->input('target_market_id');

        return [
            'product_form_id' => [
                'required',
                Rule::exists('product_forms', 'id')->where('workspace_id', $workspace->id),
            ],
            'target_market_id' => [
                'required',
                Rule::exists('target_markets', 'id')
                    ->where('workspace_id', $workspace->id)
                    ->whereNull('parent_id'),
            ],
            // Optional — a category with no sub categories has nothing to pick
            // — but when given it has to belong to the category above it, or
            // the chip in the header would read as a pairing that isn't one.
            'target_market_sub_id' => [
                'nullable',
                Rule::exists('target_markets', 'id')
                    ->where('workspace_id', $workspace->id)
                    ->where('parent_id', $categoryId),
            ],
        ];
    }
}
