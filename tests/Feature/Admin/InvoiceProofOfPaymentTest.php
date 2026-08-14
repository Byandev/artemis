<?php

use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function invoiceFor(Workspace $workspace, string $status = Invoice::STATUS_SENT): Invoice
{
    return Invoice::create([
        'number' => Invoice::nextNumber(2026),
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Acme Corp',
        'issue_date' => '2026-08-01',
        'due_date' => '2026-08-15',
        'currency' => 'PHP',
        'line_items' => [[
            'description' => 'Pro plan',
            'quantity' => 1,
            'unit_price' => 2500,
            'amount' => 2500,
        ]],
        'total' => 2500,
        'status' => $status,
    ]);
}

beforeEach(function () {
    // The media disk is whatever the environment configured; faking it keeps
    // the suite off real S3 and off the developer's filesystem.
    Storage::fake(config('filesystems.media_disk'));

    $this->admin = User::factory()->superAdmin()->create();
    $this->invoice = invoiceFor(Workspace::factory()->create());
});

it('blocks users who are not super admins', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('admin.invoices.proof.store', $this->invoice), [
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertRedirect(route('dashboard'));

    expect($this->invoice->fresh()->proofOfPayment())->toBeNull();
});

it('attaches a receipt to an invoice', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.proof.store', $this->invoice), [
            'proof' => UploadedFile::fake()->image('gcash.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $proof = $this->invoice->fresh()->proofOfPayment();

    expect($proof)->not->toBeNull()
        ->and($proof->file_name)->toBe('gcash.jpg')
        ->and($proof->collection_name)->toBe(Invoice::PROOF_COLLECTION);

    // Stored on the configured media disk, not wherever the default points.
    expect($proof->disk)->toBe(config('filesystems.media_disk'));
    Storage::disk($proof->disk)->assertExists($proof->getPathRelativeToRoot());
});

it('accepts a pdf receipt', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.proof.store', $this->invoice), [
            'proof' => UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    expect($this->invoice->fresh()->proofOfPayment()->file_name)->toBe('receipt.pdf');
});

it('rejects a file type that is not a receipt', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.proof.store', $this->invoice), [
            'proof' => UploadedFile::fake()->create('payload.exe', 10),
        ])
        ->assertSessionHasErrors('proof');

    expect($this->invoice->fresh()->proofOfPayment())->toBeNull();
});

it('rejects a file over the size limit', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.proof.store', $this->invoice), [
            'proof' => UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'),
        ])
        ->assertSessionHasErrors('proof');

    expect($this->invoice->fresh()->proofOfPayment())->toBeNull();
});

it('replaces the previous receipt instead of keeping both', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.proof.store', $this->invoice), [
            'proof' => UploadedFile::fake()->image('first.jpg'),
        ]);

    $first = $this->invoice->fresh()->proofOfPayment();

    $this->actingAs($this->admin)
        ->post(route('admin.invoices.proof.store', $this->invoice), [
            'proof' => UploadedFile::fake()->image('second.jpg'),
        ]);

    $invoice = $this->invoice->fresh();

    expect($invoice->getMedia(Invoice::PROOF_COLLECTION))->toHaveCount(1)
        ->and($invoice->proofOfPayment()->file_name)->toBe('second.jpg');

    // The superseded object is gone from storage, not just unlinked.
    Storage::disk($first->disk)->assertMissing($first->getPathRelativeToRoot());
});

it('files the receipt while marking the invoice paid', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.invoices.update-status', $this->invoice), [
            'status' => 'paid',
            'proof' => UploadedFile::fake()->image('bank-transfer.png'),
        ])
        ->assertSessionHasNoErrors();

    $invoice = $this->invoice->fresh();

    expect($invoice->status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($invoice->proofOfPayment()->file_name)->toBe('bank-transfer.png');
});

it('still allows marking paid with no receipt', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.invoices.update-status', $this->invoice), [
            'status' => 'paid',
        ])
        ->assertSessionHasNoErrors();

    $invoice = $this->invoice->fresh();

    expect($invoice->status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->proofOfPayment())->toBeNull();
});

it('does not save the status when the receipt is rejected', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.invoices.update-status', $this->invoice), [
            'status' => 'paid',
            'proof' => UploadedFile::fake()->create('payload.exe', 10),
        ])
        ->assertSessionHasErrors('proof');

    expect($this->invoice->fresh()->status)->toBe(Invoice::STATUS_SENT);
});

it('removes a receipt', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.proof.store', $this->invoice), [
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

    $proof = $this->invoice->fresh()->proofOfPayment();

    $this->actingAs($this->admin)
        ->delete(route('admin.invoices.proof.destroy', $this->invoice))
        ->assertSessionHasNoErrors();

    expect($this->invoice->fresh()->proofOfPayment())->toBeNull();
    Storage::disk($proof->disk)->assertMissing($proof->getPathRelativeToRoot());
});

it('404s when removing a receipt that was never attached', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.invoices.proof.destroy', $this->invoice))
        ->assertNotFound();
});

it('404s when viewing a receipt that was never attached', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.invoices.proof.show', $this->invoice))
        ->assertNotFound();
});

it('serves the receipt through a link that expires', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.proof.store', $this->invoice), [
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.invoices.proof.show', $this->invoice));

    // Both the s3 disk and a local disk with `serve` on can sign a URL, so the
    // handler hands one out rather than proxying the bytes.
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('expir');
});

it('streams the receipt when the disk cannot sign a url', function () {
    // Storage::fake() always yields a disk that can sign, so the fallback needs
    // a real local disk with `serve` off to exercise it.
    $root = storage_path('framework/testing/unsigned-proof');

    config(['filesystems.disks.unsigned' => [
        'driver' => 'local',
        'root' => $root,
        'serve' => false,
    ]]);
    config(['filesystems.media_disk' => 'unsigned']);

    expect(Storage::disk('unsigned')->providesTemporaryUrls())->toBeFalse();

    $this->actingAs($this->admin)
        ->post(route('admin.invoices.proof.store', $this->invoice), [
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

    $this->actingAs($this->admin)
        ->get(route('admin.invoices.proof.show', $this->invoice))
        ->assertSuccessful()
        ->assertDownload('receipt.jpg');

    File::deleteDirectory($root);
});

it('exposes the receipt on the invoices page', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.invoices.proof.store', $this->invoice), [
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

    $this->actingAs($this->admin)
        ->get(route('admin.invoices.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/invoices/index')
            ->where('invoices.data.0.proof.file_name', 'receipt.jpg')
        );
});

it('reports no receipt when none is attached', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.invoices.index'))
        ->assertInertia(fn ($page) => $page
            ->where('invoices.data.0.proof', null)
        );
});
