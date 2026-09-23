<?php

namespace Modules\Products\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Products\Exceptions\RdpSuggestionFailed;
use Modules\Products\Models\ProductForm;
use Modules\Products\Models\ProductResearch;
use Modules\Products\Models\RdpPromptSetting;
use Modules\Products\Models\TargetMarket;
use Modules\Products\Services\RdpNameSuggester;
use Modules\Products\Services\RdpPackshotGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RdpBuilderController extends Controller
{
    use AuthorizesRequests;

    /** The RDPs list. */
    public function index(Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ViewRdpBuilder->value, $workspace);

        $rdps = ProductResearch::ofWorkspace($workspace)
            ->with(['form:id,name', 'targetMarket:id,name', 'targetMarketSub:id,name', 'creator:id,name'])
            ->latest()
            ->get()
            ->map(fn (ProductResearch $rdp) => [
                'id' => $rdp->id,
                'name' => $rdp->name,
                'form' => $rdp->form?->name,
                // The list shows the sub category — "Back Pain", not
                // "Musculoskeletal" — falling back to the category when the
                // brief was filed without one.
                'target_market' => $rdp->targetMarketSub?->name ?? $rdp->targetMarket?->name,
                'date' => $rdp->created_at?->toDateString(),
                'created_by' => $rdp->creator?->name,
            ]);

        return Inertia::render('workspaces/products/rdp-builder/index', [
            'workspace' => $workspace,
            'rdps' => $rdps,
        ]);
    }

    /** The builder, on a brief that does not exist yet. */
    public function create(Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageRdpBuilder->value, $workspace);

        return Inertia::render('workspaces/products/rdp-builder/builder', [
            'workspace' => $workspace,
            'rdp' => null,
            ...$this->options($workspace),
        ]);
    }

    /** The builder, on a brief already filed. */
    public function edit(Workspace $workspace, ProductResearch $rdp)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ViewRdpBuilder->value, $workspace);
        $this->guard($workspace, $rdp);

        return Inertia::render('workspaces/products/rdp-builder/builder', [
            'workspace' => $workspace,
            'rdp' => [
                'id' => $rdp->id,
                'product_form_id' => $rdp->product_form_id,
                'target_market_id' => $rdp->target_market_id,
                'target_market_sub_id' => $rdp->target_market_sub_id,
                'name' => $rdp->name,
                'positioning' => $rdp->positioning,
                'claims' => $rdp->claims,
                'active_ingredients' => $rdp->active_ingredients,
                'additional_instruction' => $rdp->additional_instruction,
                ...$this->packshotPayload($workspace, $rdp),
            ],
            ...$this->options($workspace),
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageRdpBuilder->value, $workspace);

        $validated = $request->validate($this->rules($request, $workspace));

        $rdp = ProductResearch::create([
            ...$validated,
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('workspaces.products.rdp-builder.index', $workspace)
            ->with('success', "\"{$rdp->name}\" saved to RDPs.");
    }

    public function update(Request $request, Workspace $workspace, ProductResearch $rdp)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageRdpBuilder->value, $workspace);
        $this->guard($workspace, $rdp);

        $rdp->update($request->validate($this->rules($request, $workspace)));

        return redirect()
            ->route('workspaces.products.rdp-builder.index', $workspace)
            ->with('success', "\"{$rdp->name}\" updated.");
    }

    /**
     * Candidate names and a positioning line for the brief so far — as many as
     * the workspace asked for in the Configure prompt dialog.
     *
     * Gated on ManageRdpBuilder rather than ViewRdpBuilder: every press of the
     * button is a paid call, so someone who may only read the list must not be
     * able to spend against the workspace's account.
     */
    public function suggestNames(Request $request, Workspace $workspace, RdpNameSuggester $suggester)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageRdpBuilder->value, $workspace);

        $validated = $request->validate($this->briefRules($request, $workspace));

        if (! RdpNameSuggester::isConfigured()) {
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

        $settings = RdpPromptSetting::forWorkspace($workspace);

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
        } catch (RdpSuggestionFailed $e) {
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
    public function generatePackshots(Request $request, Workspace $workspace, RdpPackshotGenerator $generator)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageRdpBuilder->value, $workspace);

        $rdp = $this->resolveRdp($request, $workspace);

        if (! RdpPackshotGenerator::isConfigured()) {
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

        $rdp->loadMissing(['form', 'targetMarket', 'targetMarketSub']);
        $settings = RdpPromptSetting::forWorkspace($workspace);

        try {
            $images = $generator->generate(
                $rdp->name,
                $rdp->form?->name ?? 'product',
                // The form says how it is packaged; the generator no longer
                // guesses from the name.
                $rdp->form?->packshotDescription() ?? 'the product in its retail packaging',
                // Category and sub category stay separate: the category picks
                // the palette, the sub category says what it treats.
                $rdp->targetMarket?->name ?? 'general wellness',
                $rdp->targetMarketSub?->name,
                $settings->packshotPrompt(),
                $settings->packshotCount(),
            );
        } catch (RdpSuggestionFailed $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        // A fresh run replaces the previous options rather than piling up: the
        // grid shows one set, and the old files would otherwise sit in the
        // bucket unreachable.
        $rdp->clearMediaCollection(ProductResearch::PACKSHOT_OPTIONS_COLLECTION);

        foreach ($images as $index => $image) {
            $rdp->addMediaFromString($image['data'])
                ->usingFileName(Str::slug($rdp->name ?: 'packshot')."-{$index}.".$this->extensionFor($image['mime']))
                ->toMediaCollection(ProductResearch::PACKSHOT_OPTIONS_COLLECTION);
        }

        return response()->json($this->packshotPayload($workspace, $rdp->refresh()));
    }

    /** Someone's own render, used instead of a generated one. */
    public function uploadPackshot(Request $request, Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageRdpBuilder->value, $workspace);

        $request->validate([
            'packshot' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,svg', 'max:10240'],
        ]);

        $rdp = $this->resolveRdp($request, $workspace);

        // singleFile(), so this replaces whatever was there.
        $rdp->addMediaFromRequest('packshot')->toMediaCollection(ProductResearch::PACKSHOT_COLLECTION);

        return response()->json($this->packshotPayload($workspace, $rdp->refresh()));
    }

    /** Promote one of the generated options to be the brief's packshot. */
    public function selectPackshot(Request $request, Workspace $workspace, ProductResearch $rdp)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageRdpBuilder->value, $workspace);
        $this->guard($workspace, $rdp);

        $validated = $request->validate(['media_id' => ['required', 'integer']]);

        // Looked up through the brief's own options rather than by id alone, so
        // another brief's file cannot be adopted.
        $option = $rdp->getMedia(ProductResearch::PACKSHOT_OPTIONS_COLLECTION)
            ->firstWhere('id', $validated['media_id']);

        abort_unless($option !== null, 404);

        // Copied rather than moved: the option stays in the grid so a different
        // one can be picked afterwards.
        $option->copy($rdp, ProductResearch::PACKSHOT_COLLECTION, $option->disk);

        return response()->json($this->packshotPayload($workspace, $rdp->refresh()));
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
    private function resolveRdp(Request $request, Workspace $workspace): ProductResearch
    {
        if ($request->filled('rdp_id')) {
            $rdp = ProductResearch::find($request->integer('rdp_id'));
            $this->guard($workspace, $rdp ?? new ProductResearch);

            return $rdp;
        }

        $validated = $request->validate($this->rules($request, $workspace));

        return ProductResearch::create([
            ...$validated,
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
        ]);
    }

    /**
     * Serve a packshot or one of its options. The bucket is private, so this
     * hands out a short-lived signed URL, or streams the bytes when the disk
     * cannot sign one.
     */
    public function showPackshotImage(Workspace $workspace, ProductResearch $rdp, Media $media)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ViewRdpBuilder->value, $workspace);
        $this->guard($workspace, $rdp);

        abort_unless(
            $media->model_type === $rdp->getMorphClass() && $media->model_id === $rdp->getKey(),
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
    private function packshotPayload(Workspace $workspace, ProductResearch $rdp): array
    {
        $url = fn (Media $media) => route('workspaces.products.rdp-builder.packshot-image', [
            'workspace' => $workspace,
            'rdp' => $rdp->id,
            'media' => $media->id,
        ]);

        $packshot = $rdp->getFirstMedia(ProductResearch::PACKSHOT_COLLECTION);

        return [
            // Handed back so a builder that was still a draft knows which
            // brief it is now editing.
            'rdp_id' => $rdp->id,
            'packshot' => $packshot ? ['id' => $packshot->id, 'url' => $url($packshot)] : null,
            'packshot_options' => $rdp->getMedia(ProductResearch::PACKSHOT_OPTIONS_COLLECTION)
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
     * Save the Configure prompt dialog.
     *
     * Workspace-wide rather than per brief: it is the brand's voice, so every
     * brief should inherit it.
     */
    public function updatePromptSettings(Request $request, Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageRdpBuilder->value, $workspace);

        // `sometimes` throughout: the dialog sends both halves, but a caller
        // changing only the naming settings must not have to restate the
        // packshot ones — and must not have them wiped for leaving them out.
        $validated = $request->validate([
            // Null is "reset to default" — the model falls back rather than
            // storing a copy of the default that would then never track it.
            'naming_prompt' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'name_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:'.RdpPromptSetting::MAX_COUNT],
            'packshot_prompt' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'packshot_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:'.RdpPromptSetting::MAX_PACKSHOT_COUNT],
        ]);

        $settings = RdpPromptSetting::forWorkspace($workspace);

        // Storing the default verbatim would freeze the workspace on today's
        // wording, so it is normalised back to null.
        $normalise = function (string $value, string $default): ?string {
            $value = trim($value);

            return ($value === '' || $value === $default) ? null : $value;
        };

        if (array_key_exists('naming_prompt', $validated)) {
            $settings->naming_prompt = $normalise(
                (string) $validated['naming_prompt'],
                RdpPromptSetting::DEFAULT_PROMPT
            );
        }

        if (array_key_exists('packshot_prompt', $validated)) {
            $settings->packshot_prompt = $normalise(
                (string) $validated['packshot_prompt'],
                RdpPromptSetting::DEFAULT_PACKSHOT_PROMPT
            );
        }

        $settings->fill(array_intersect_key(
            $validated,
            array_flip(['name_count', 'packshot_count'])
        ))->save();

        return response()->json($this->promptSettings($workspace));
    }

    /**
     * What the Configure prompt dialog opens on. `is_default` drives whether
     * "Reset to default" has anything to undo.
     *
     * @return array<string, mixed>
     */
    private function promptSettings(Workspace $workspace): array
    {
        $settings = RdpPromptSetting::forWorkspace($workspace);

        return [
            'naming_prompt' => $settings->prompt(),
            'name_count' => $settings->count(),
            'default_prompt' => RdpPromptSetting::DEFAULT_PROMPT,
            'max_count' => RdpPromptSetting::MAX_COUNT,
            'is_default' => blank($settings->naming_prompt),
            'packshot_prompt' => $settings->packshotPrompt(),
            'packshot_count' => $settings->packshotCount(),
            'default_packshot_prompt' => RdpPromptSetting::DEFAULT_PACKSHOT_PROMPT,
            'max_packshot_count' => RdpPromptSetting::MAX_PACKSHOT_COUNT,
            'packshot_is_default' => blank($settings->packshot_prompt),
        ];
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
    private function guard(Workspace $workspace, ?ProductResearch $rdp): void
    {
        abort_unless($rdp !== null && $rdp->workspace_id === $workspace->id, 404);
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
            'promptSettings' => $this->promptSettings($workspace),
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
