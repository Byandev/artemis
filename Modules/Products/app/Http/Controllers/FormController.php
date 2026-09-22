<?php

namespace Modules\Products\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Products\Models\ProductForm;
use Modules\Products\Models\ProductFormVariant;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FormController extends Controller
{
    use AuthorizesRequests;

    public function index(Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ViewProductForms->value, $workspace);

        $forms = ProductForm::ofWorkspace($workspace)
            ->with(['variants.media'])
            ->withCount(['products', 'variants'])
            ->orderBy('name')
            ->get()
            ->map(fn (ProductForm $form) => $this->present($workspace, $form));

        return Inertia::render('workspaces/products/forms/index', [
            'workspace' => $workspace,
            'forms' => $forms,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageProductForms->value, $workspace);

        $validated = $request->validate($this->rules($workspace));

        $form = DB::transaction(function () use ($request, $workspace, $validated) {
            $form = ProductForm::create([
                'workspace_id' => $workspace->id,
                'name' => $validated['name'],
            ]);

            $this->syncVariants($request, $form);

            return $form;
        });

        return redirect()
            ->route('workspaces.products.forms.index', $workspace)
            ->with('success', "Form \"{$form->name}\" created.");
    }

    public function update(Request $request, Workspace $workspace, ProductForm $productForm)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageProductForms->value, $workspace);
        $this->guard($workspace, $productForm);

        $validated = $request->validate($this->rules($workspace, $productForm));

        DB::transaction(function () use ($request, $productForm, $validated) {
            $productForm->update(['name' => $validated['name']]);

            $this->syncVariants($request, $productForm);
        });

        return redirect()
            ->route('workspaces.products.forms.index', $workspace)
            ->with('success', "Form \"{$productForm->name}\" updated.");
    }

    public function destroy(Workspace $workspace, ProductForm $productForm)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ManageProductForms->value, $workspace);
        $this->guard($workspace, $productForm);

        $name = $productForm->name;

        // Variants cascade with the form; deleting each one through the model
        // rather than the database so media-library clears their pictures out
        // of the bucket instead of leaving them orphaned.
        DB::transaction(function () use ($productForm) {
            $productForm->variants->each->delete();
            $productForm->delete();
        });

        return redirect()
            ->route('workspaces.products.forms.index', $workspace)
            ->with('success', "Form \"{$name}\" deleted.");
    }

    /**
     * Serve a variant's picture. The bucket is private, so this either hands
     * out a short-lived signed URL or streams the bytes when the disk cannot
     * sign one (a local disk in development) — the file is never publicly
     * readable.
     */
    public function showVariantImage(Workspace $workspace, ProductFormVariant $variant, Media $media)
    {
        $this->guardModule($workspace);
        $this->authorize(Permission::ViewProductForms->value, $workspace);

        $variant->loadMissing('form');
        $this->guard($workspace, $variant->form);

        // Route-model binding resolves the media row by id alone, so without
        // this any member could pull another variant's file by guessing an id.
        abort_unless(
            $media->model_type === $variant->getMorphClass() && $media->model_id === $variant->getKey(),
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
     * Workspace owners hold '*', so the permission checks above wave them
     * through whether or not the workspace bought the module.
     */
    private function guardModule(Workspace $workspace): void
    {
        abort_unless($workspace->products_module_enabled, 404);
    }

    /**
     * Route-model binding resolves a form by id alone, so a member of one
     * workspace could otherwise reach another workspace's forms.
     */
    private function guard(Workspace $workspace, ?ProductForm $form): void
    {
        abort_unless($form !== null && $form->workspace_id === $workspace->id, 404);
    }

    /**
     * Shared between store and update so the two can't drift on what they
     * accept. Mime enforcement lives here rather than on the variant's media
     * collection: a validation failure is a field error the user can act on,
     * where media-library's own check throws a 500.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(Workspace $workspace, ?ProductForm $form = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_forms', 'name')
                    ->where('workspace_id', $workspace->id)
                    ->ignore($form?->id),
            ],
            'variants' => ['nullable', 'array'],
            // The id of a size already saved on this form. Anything else is
            // ignored by syncVariants(), which only matches ids it owns.
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            // `heif` alongside `heic` because iOS photos are routinely
            // detected as the former.
            'variants.*.image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:10240'],
            // Set when the user clears a saved picture without picking a
            // replacement.
            'variants.*.remove_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Bring the form's sizes in line with what was submitted: update the ones
     * that came back with an id, create the rest, and drop whatever is missing.
     */
    private function syncVariants(Request $request, ProductForm $form): void
    {
        // Read the rows off the request rather than out of the validated
        // payload. Validation rebuilds the array rule by rule, so a wildcard
        // that matches only some of the rows (`variants.*.id`, which only the
        // saved ones carry) puts those rows into the array first — the order
        // the user typed comes back shuffled, and the file lookup below, which
        // is keyed on the index the upload arrived under, would then attach
        // each picture to the wrong size.
        $submitted = $request->input('variants', []);
        $submitted = is_array($submitted) ? $submitted : [];
        ksort($submitted, SORT_NUMERIC);

        $existing = $form->variants()->get()->keyBy('id');
        $kept = [];
        $position = 0;

        foreach ($submitted as $index => $variant) {
            // Only ids already on this form count — a submitted id belonging to
            // another form would otherwise be stolen into this one.
            $model = isset($variant['id']) ? $existing->get((int) $variant['id']) : null;

            if ($model) {
                $model->update([
                    'name' => $variant['name'],
                    'position' => $position,
                ]);
            } else {
                $model = $form->variants()->create([
                    'name' => $variant['name'],
                    'position' => $position,
                ]);
            }

            $kept[] = $model->id;

            $this->attachImage($request, $model, $index, $variant);

            $position++;
        }

        // Whatever the dialog removed. Deleted through the model so their
        // pictures leave the bucket with them.
        $existing->except($kept)->each->delete();
    }

    /**
     * @param  array<string, mixed>  $variant
     */
    private function attachImage(Request $request, ProductFormVariant $model, int|string $index, array $variant): void
    {
        // Keyed on the index the row arrived under, which is the only thing
        // that ties an upload to its row.
        $file = $request->file("variants.{$index}.image");

        if ($file) {
            // The collection is singleFile(), so this replaces any existing
            // picture and deletes the old object from the bucket.
            $model->addMedia($file)->toMediaCollection(ProductFormVariant::IMAGE_COLLECTION);

            return;
        }

        // Checked only when no file was sent, so a user who clears a picture
        // and then chooses a new one in the same edit keeps the new one.
        if (filter_var($variant['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $model->clearMediaCollection(ProductFormVariant::IMAGE_COLLECTION);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Workspace $workspace, ProductForm $form): array
    {
        return [
            'id' => $form->id,
            'name' => $form->name,
            'products_count' => $form->products_count,
            'variants_count' => $form->variants_count,
            'variants' => $form->variants->map(function (ProductFormVariant $variant) use ($workspace) {
                $media = $variant->getFirstMedia(ProductFormVariant::IMAGE_COLLECTION);

                return [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'image' => $media ? [
                        'id' => $media->id,
                        'file_name' => $media->file_name,
                        'size' => $media->size,
                        'url' => route('workspaces.products.forms.variant-image', [
                            'workspace' => $workspace,
                            'variant' => $variant->id,
                            'media' => $media->id,
                        ]),
                    ] : null,
                ];
            })->values(),
        ];
    }
}
