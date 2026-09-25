<?php

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Products\Database\Seeders\ProductFormSeeder;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductForm;
use Modules\Products\Models\ProductFormVariant;
use Tests\TestCase;

// Module test dirs aren't bound by the root tests/Pest.php (->in('Feature') only
// covers tests/Feature), so extend the app TestCase explicitly to boot the app.
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // The variant collection is pinned to whatever this points at, so faking
    // the bucket keeps uploads out of the real one while still exercising the
    // same path. Faked under the disk's own name because media-library looks
    // it up in filesystems.disks, which Storage::fake() only swaps in place.
    config(['filesystems.product_form_media_disk' => 's3']);
    Storage::fake('s3');
});

test('owner can view the product forms page', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/forms")
        ->assertOk();
});

test('the page 404s for a workspace without the products module', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // Owners hold '*', so the permission check alone would wave them through.
    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/forms")
        ->assertNotFound();
});

test('non-member cannot view product forms', function () {
    ['workspace' => $workspace] = makeProductsWorkspace();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/workspaces/{$workspace->slug}/products/forms")
        ->assertForbidden();
});

test('viewing and managing are separate permissions', function () {
    ['workspace' => $workspace] = makeProductsWorkspace();

    $viewer = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewProductForms->value],
        'Products',
    );

    $this->actingAs($viewer)
        ->get("/workspaces/{$workspace->slug}/products/forms")
        ->assertOk();

    $this->actingAs($viewer)
        ->post("/workspaces/{$workspace->slug}/products/forms", ['name' => 'Oil'])
        ->assertForbidden();

    $manager = makeMemberWithPermissions(
        $workspace,
        [Permission::ViewProductForms->value, Permission::ManageProductForms->value],
        'Products',
    );

    $this->actingAs($manager)
        ->post("/workspaces/{$workspace->slug}/products/forms", ['name' => 'Oil'])
        ->assertRedirect("/workspaces/{$workspace->slug}/products/forms");
});

test('store creates a form with its sizes and puts each picture on the bucket', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/forms", [
            'name' => 'Oil',
            'variants' => [
                ['name' => '30ml', 'image' => UploadedFile::fake()->image('30ml.jpg')],
                ['name' => '60ml'],
            ],
        ])
        ->assertRedirect("/workspaces/{$workspace->slug}/products/forms");

    $form = ProductForm::where('workspace_id', $workspace->id)->firstOrFail();

    expect($form->name)->toBe('Oil')
        ->and($form->variants->pluck('name')->all())->toBe(['30ml', '60ml']);

    $media = $form->variants->first()->getFirstMedia(ProductFormVariant::IMAGE_COLLECTION);

    expect($media)->not->toBeNull()
        ->and($media->disk)->toBe('s3');

    Storage::disk('s3')->assertExists($media->getPathRelativeToRoot());

    // The size with no picture stays pictureless rather than inheriting one.
    expect($form->variants->last()->getFirstMedia(ProductFormVariant::IMAGE_COLLECTION))->toBeNull();
});

test('form name must be unique within a workspace but can repeat across workspaces', function () {
    ['user' => $ownerA, 'workspace' => $workspaceA] = makeProductsWorkspace();
    ['user' => $ownerB, 'workspace' => $workspaceB] = makeProductsWorkspace();

    ProductForm::create(['workspace_id' => $workspaceA->id, 'name' => 'Spray']);

    $this->actingAs($ownerA)
        ->post("/workspaces/{$workspaceA->slug}/products/forms", ['name' => 'Spray'])
        ->assertSessionHasErrors('name');

    $this->actingAs($ownerB)
        ->post("/workspaces/{$workspaceB->slug}/products/forms", ['name' => 'Spray'])
        ->assertSessionHasNoErrors();
});

test('store rejects a nameless size and a non-image file', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products/forms", [
            'name' => 'Patch',
            'variants' => [
                ['name' => ''],
                ['name' => '2ml', 'image' => UploadedFile::fake()->create('notes.pdf', 12, 'application/pdf')],
            ],
        ])
        ->assertSessionHasErrors(['variants.0.name', 'variants.1.image']);
});

test('update renames, reorders, adds and drops sizes', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $form = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Oil']);
    $keep = $form->variants()->create(['name' => '30ml', 'position' => 0]);
    $drop = $form->variants()->create(['name' => '60ml', 'position' => 1]);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/forms/{$form->id}", [
            'name' => 'Oil Tincture',
            'variants' => [
                ['name' => '100ml'],
                ['id' => $keep->id, 'name' => '30ml'],
            ],
        ])
        ->assertRedirect("/workspaces/{$workspace->slug}/products/forms");

    $form->refresh()->load('variants');

    expect($form->name)->toBe('Oil Tincture')
        // Ordered by the position the dialog submitted them in.
        ->and($form->variants->pluck('name')->all())->toBe(['100ml', '30ml'])
        ->and(ProductFormVariant::find($drop->id))->toBeNull();
});

test('a picture lands on the size it was picked for, not the row beside it', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $form = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Balm']);
    $saved = $form->variants()->create(['name' => '20g', 'position' => 0]);

    // A new row first and a saved one second: the shape that comes back from
    // validation reversed, which would send the upload to the wrong size.
    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/forms/{$form->id}", [
            'name' => 'Balm',
            'variants' => [
                ['name' => '50g', 'image' => UploadedFile::fake()->image('fifty.jpg')],
                ['id' => $saved->id, 'name' => '20g'],
            ],
        ])
        ->assertSessionHasNoErrors();

    $form->refresh()->load('variants');
    $fifty = $form->variants->firstWhere('name', '50g');

    expect($form->variants->pluck('name')->all())->toBe(['50g', '20g'])
        ->and($fifty->getFirstMedia(ProductFormVariant::IMAGE_COLLECTION)?->file_name)->toBe('fifty.jpg')
        ->and($saved->refresh()->getFirstMedia(ProductFormVariant::IMAGE_COLLECTION))->toBeNull();
});

test('update replaces a picture and can clear one', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $form = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Cream']);
    $variant = $form->variants()->create(['name' => '50g', 'position' => 0]);
    $variant->addMedia(UploadedFile::fake()->image('old.jpg'))
        ->toMediaCollection(ProductFormVariant::IMAGE_COLLECTION);

    $old = $variant->getFirstMedia(ProductFormVariant::IMAGE_COLLECTION);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/forms/{$form->id}", [
            'name' => 'Cream',
            'variants' => [
                ['id' => $variant->id, 'name' => '50g', 'image' => UploadedFile::fake()->image('new.jpg')],
            ],
        ])
        ->assertSessionHasNoErrors();

    $new = $variant->refresh()->getFirstMedia(ProductFormVariant::IMAGE_COLLECTION);

    expect($new)->not->toBeNull()
        ->and($new->file_name)->toBe('new.jpg')
        // singleFile(), so the replaced object leaves the bucket.
        ->and($new->id)->not->toBe($old->id);

    Storage::disk('s3')->assertMissing($old->getPathRelativeToRoot());

    // And clearing without a replacement.
    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/forms/{$form->id}", [
            'name' => 'Cream',
            'variants' => [
                ['id' => $variant->id, 'name' => '50g', 'remove_image' => true],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($variant->refresh()->getFirstMedia(ProductFormVariant::IMAGE_COLLECTION))->toBeNull();
});

test('a size id from another form cannot be stolen', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $mine = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Oil']);
    $other = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Spray']);
    $theirs = $other->variants()->create(['name' => '10ml', 'position' => 0]);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/forms/{$mine->id}", [
            'name' => 'Oil',
            'variants' => [['id' => $theirs->id, 'name' => 'Hijacked']],
        ])
        ->assertSessionHasNoErrors();

    // The submitted id was ignored; a new size was created instead.
    expect($theirs->refresh()->name)->toBe('10ml')
        ->and($theirs->product_form_id)->toBe($other->id)
        ->and($mine->variants()->pluck('name')->all())->toBe(['Hijacked']);
});

test('a form from another workspace is not reachable', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();
    ['workspace' => $other] = makeProductsWorkspace();

    $foreign = ProductForm::create(['workspace_id' => $other->id, 'name' => 'Oil']);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/forms/{$foreign->id}", ['name' => 'Mine'])
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}/products/forms/{$foreign->id}")
        ->assertNotFound();
});

test('deleting a form clears its pictures and releases its products', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $form = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Gel']);
    $variant = $form->variants()->create(['name' => '15g', 'position' => 0]);
    $variant->addMedia(UploadedFile::fake()->image('gel.jpg'))
        ->toMediaCollection(ProductFormVariant::IMAGE_COLLECTION);
    $path = $variant->getFirstMedia(ProductFormVariant::IMAGE_COLLECTION)->getPathRelativeToRoot();

    $product = Product::factory()->create([
        'workspace_id' => $workspace->id,
        'owner_id' => $owner->id,
        'product_form_id' => $form->id,
    ]);

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}/products/forms/{$form->id}")
        ->assertRedirect("/workspaces/{$workspace->slug}/products/forms");

    expect(ProductForm::find($form->id))->toBeNull()
        ->and(ProductFormVariant::find($variant->id))->toBeNull()
        // The product survives, just without a form.
        ->and($product->refresh()->product_form_id)->toBeNull();

    Storage::disk('s3')->assertMissing($path);
});

test('index reports the product and variant counts the table shows', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();

    $form = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Oil']);
    $form->variants()->create(['name' => '30ml', 'position' => 0]);
    $form->variants()->create(['name' => '60ml', 'position' => 1]);

    Product::factory()->count(3)->create([
        'workspace_id' => $workspace->id,
        'owner_id' => $owner->id,
        'product_form_id' => $form->id,
    ]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/forms")
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/products/forms/index')
            ->where('forms.0.products_count', 3)
            ->where('forms.0.variants_count', 2)
        );
});

test('a variant picture is served through the app, and not across workspaces', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();
    ['user' => $stranger] = makeProductsWorkspace();

    $form = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Oil']);
    $variant = $form->variants()->create(['name' => '30ml', 'position' => 0]);
    $variant->addMedia(UploadedFile::fake()->image('oil.jpg'))
        ->toMediaCollection(ProductFormVariant::IMAGE_COLLECTION);
    $media = $variant->getFirstMedia(ProductFormVariant::IMAGE_COLLECTION);

    $url = "/workspaces/{$workspace->slug}/products/forms/variants/{$variant->id}/image/{$media->id}";

    // A bucket that can sign hands back a 302 to the short-lived URL rather
    // than the bytes — Storage::fake() signs too, so this is the path
    // production takes.
    $this->actingAs($owner)->get($url)->assertRedirect();

    // A member of another workspace has no business with this file.
    $this->actingAs($stranger)->get($url)->assertForbidden();

    // Nor can the variant be paired with a media row it does not own.
    $otherForm = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Spray']);
    $otherVariant = $otherForm->variants()->create(['name' => '10ml', 'position' => 0]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/forms/variants/{$otherVariant->id}/image/{$media->id}")
        ->assertNotFound();
});

test('a product can be filed under a form, and only one from its own workspace', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeProductsWorkspace();
    ['workspace' => $other] = makeProductsWorkspace();

    $mine = ProductForm::create(['workspace_id' => $workspace->id, 'name' => 'Oil']);
    $theirs = ProductForm::create(['workspace_id' => $other->id, 'name' => 'Oil']);

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/products", [
            'name' => 'Widget',
            'code' => 'WID-1',
            'category' => 'Gadgets',
            'status' => 'Scaling',
            'product_form_id' => $mine->id,
        ])
        ->assertSessionHasNoErrors();

    $product = Product::where('workspace_id', $workspace->id)->firstOrFail();
    expect($product->product_form_id)->toBe($mine->id);

    // And the count the forms table shows follows it.
    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/products/forms")
        ->assertInertia(fn ($page) => $page->where('forms.0.products_count', 1));

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/products/{$product->id}", [
            'name' => 'Widget',
            'code' => 'WID-1',
            'category' => 'Gadgets',
            'status' => 'Scaling',
            'product_form_id' => $theirs->id,
        ])
        ->assertSessionHasErrors('product_form_id');
});

test('the seeder fills a products workspace with the standard delivery formats', function () {
    ['workspace' => $workspace] = makeProductsWorkspace();
    ['workspace' => $without] = makeWorkspaceWithOwner();

    (new ProductFormSeeder)->run();

    expect(ProductForm::ofWorkspace($workspace)->orderBy('id')->pluck('name')->all())
        ->toBe(ProductFormSeeder::FORMS)
        // A workspace without the module is left alone.
        ->and(ProductForm::where('workspace_id', $without->id)->count())->toBe(0);
});

test('re-running the form seeder leaves edited forms and their sizes alone', function () {
    ['workspace' => $workspace] = makeProductsWorkspace();

    (new ProductFormSeeder)->run();

    $oil = ProductForm::ofWorkspace($workspace)->where('name', 'Oil')->firstOrFail();
    $oil->variants()->create(['name' => '30ml', 'position' => 0]);

    (new ProductFormSeeder)->run();

    expect(ProductForm::ofWorkspace($workspace)->count())->toBe(count(ProductFormSeeder::FORMS))
        ->and($oil->refresh()->variants()->pluck('name')->all())->toBe(['30ml']);
});
