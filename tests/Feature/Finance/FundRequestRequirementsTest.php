<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Models\FundRequest;
use Modules\Finance\Models\FundRequestAttachment;
use Modules\Finance\Models\TransactionType;

beforeEach(function () {
    $this->disk = config('filesystems.fund_request_attachment_disk');
    Storage::fake($this->disk);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'finance_module_enabled' => true,
    ]);
    $this->url = "/workspaces/{$this->workspace->slug}/finance/request-funds";

    $this->adSpent = TransactionType::create(['workspace_id' => $this->workspace->id, 'name' => 'Ad Spent', 'nature' => 'debit', 'fund_requestable' => true]);
    $this->statement = $this->adSpent->attachments()->create(['workspace_id' => $this->workspace->id, 'name' => 'Bank Statement']);
    $this->tracker = $this->adSpent->attachments()->create(['workspace_id' => $this->workspace->id, 'name' => 'Liquidation Tracker']);
    $this->budget = $this->adSpent->checklists()->create(['workspace_id' => $this->workspace->id, 'name' => 'Budget']);

    $this->salary = TransactionType::create(['workspace_id' => $this->workspace->id, 'name' => 'Salary', 'nature' => 'debit', 'fund_requestable' => true]);
    $this->payslip = $this->salary->attachments()->create(['workspace_id' => $this->workspace->id, 'name' => 'Payslip']);
    $this->approved = $this->salary->checklists()->create(['workspace_id' => $this->workspace->id, 'name' => 'Approved']);
});

/** A valid request body for the given type, with extra fields merged in. */
function requirementsPayload(TransactionType $type, array $extra = []): array
{
    return [
        'transaction_type_id' => $type->id,
        'payment_method' => 'cash',
        'particulars' => [['name' => 'Item', 'quantity' => 1, 'unit_price' => 500]],
        'charge_to' => [['user_id' => test()->user->id]],
        ...$extra,
    ];
}

/** A file for every attachment Ad Spent calls for, named after `$prefix`. */
function adSpentFiles(string $prefix = ''): array
{
    return [
        test()->statement->id => UploadedFile::fake()->create("{$prefix}statement.pdf", 10, 'application/pdf'),
        test()->tracker->id => UploadedFile::fake()->create("{$prefix}tracker.pdf", 10, 'application/pdf'),
    ];
}

/** The request's uploaded files, each tagged with the requirement it answers. */
function requestMedia(FundRequest $request)
{
    return $request->attachments()->with('media')->get()
        ->map(fn (FundRequestAttachment $attachment) => tap($attachment->file(), fn ($media) => $media->requirement_id = $attachment->attachment_requirement_id));
}

test('the create page lists each type with its attachments and checklist', function () {
    $this->actingAs($this->user)
        ->get("{$this->url}/create")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('workspaces/finance/request-funds/create')
            ->where('transactionTypes.0.name', 'Ad Spent')
            ->has('transactionTypes.0.attachments', 2)
            ->where('transactionTypes.0.checklists.0.name', 'Budget')
            ->where('transactionTypes.1.attachments.0.name', 'Payslip'));
});

test('stores uploaded attachments on the attachment disk and ticks the checklist', function () {
    $this->actingAs($this->user)
        ->post($this->url, requirementsPayload($this->adSpent, [
            'checklist_ids' => [$this->budget->id],
            'attachments' => adSpentFiles(),
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $request = FundRequest::sole();
    expect(requestMedia($request))->toHaveCount(2);
    $media = requestMedia($request)->first(fn ($m) => $m->requirement_id === $this->statement->id);

    expect($media->disk)->toBe($this->disk)
        ->and($media->file_name)->toBe('statement.pdf')
        ->and($media->requirement_id)->toBe($this->statement->id)
        ->and($request->checkedChecklists()->pluck('name')->all())->toBe(['Budget']);
    Storage::disk($this->disk)->assertExists($media->getPathRelativeToRoot());
});

test('rejects a file for an attachment the type does not call for', function () {
    $this->actingAs($this->user)
        ->post($this->url, requirementsPayload($this->adSpent, [
            'attachments' => [$this->payslip->id => UploadedFile::fake()->create('payslip.pdf', 10, 'application/pdf')],
        ]))
        ->assertSessionHasErrors("attachments.{$this->payslip->id}");

    expect(FundRequest::count())->toBe(0);
});

test('rejects a checklist item from another type', function () {
    $this->actingAs($this->user)
        ->post($this->url, requirementsPayload($this->adSpent, ['checklist_ids' => [$this->approved->id]]))
        ->assertSessionHasErrors('checklist_ids.0');
});

test('rejects a file type that is not allowed', function () {
    $this->actingAs($this->user)
        ->post($this->url, requirementsPayload($this->adSpent, [
            'attachments' => [$this->statement->id => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')],
        ]))
        ->assertSessionHasErrors("attachments.{$this->statement->id}");
});

test('an edit replaces a file and updates the checklist', function () {
    $this->actingAs($this->user)->post($this->url, requirementsPayload($this->adSpent, [
        'checklist_ids' => [$this->budget->id],
        'attachments' => adSpentFiles('old-'),
    ]));
    $request = FundRequest::sole();
    $oldStatement = requestMedia($request)->first(fn ($m) => $m->requirement_id === $this->statement->id);

    // Multipart can't be PUT, so the form POSTs with method spoofing.
    $this->actingAs($this->user)
        ->post("{$this->url}/{$request->id}", requirementsPayload($this->adSpent, [
            '_method' => 'put',
            'attachments' => [$this->statement->id => UploadedFile::fake()->create('new-statement.pdf', 10, 'application/pdf')],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(requestMedia($request)->pluck('file_name')->sort()->values()->all())->toBe(['new-statement.pdf', 'old-tracker.pdf'])
        ->and($request->checkedChecklists()->count())->toBe(0);
    Storage::disk($this->disk)->assertMissing($oldStatement->getPathRelativeToRoot());
});

test('cannot save without a file for every attachment the type calls for', function () {
    $this->actingAs($this->user)
        ->post($this->url, requirementsPayload($this->adSpent, [
            'attachments' => [$this->statement->id => UploadedFile::fake()->create('statement.pdf', 10, 'application/pdf')],
        ]))
        ->assertSessionHasErrors(["attachments.{$this->tracker->id}" => 'Upload the Liquidation Tracker.']);

    $this->actingAs($this->user)
        ->post($this->url, requirementsPayload($this->adSpent))
        ->assertSessionHasErrors(["attachments.{$this->statement->id}", "attachments.{$this->tracker->id}"]);

    expect(FundRequest::count())->toBe(0);
});

test('a type with no attachments needs no files', function () {
    $bare = TransactionType::create(['workspace_id' => $this->workspace->id, 'name' => 'Refund', 'nature' => 'credit', 'fund_requestable' => true]);

    $this->actingAs($this->user)
        ->post($this->url, requirementsPayload($bare))
        ->assertSessionHasNoErrors();

    expect(FundRequest::count())->toBe(1);
});

test('an edit cannot remove a required file without replacing it', function () {
    $this->actingAs($this->user)->post($this->url, requirementsPayload($this->adSpent, ['attachments' => adSpentFiles()]));
    $request = FundRequest::sole();

    $this->actingAs($this->user)
        ->put("{$this->url}/{$request->id}", requirementsPayload($this->adSpent, ['remove_attachments' => [$this->tracker->id]]))
        ->assertSessionHasErrors("attachments.{$this->tracker->id}");

    expect(requestMedia($request))->toHaveCount(2);
});

test('an edit without files keeps the ones on file', function () {
    $this->actingAs($this->user)->post($this->url, requirementsPayload($this->adSpent, [
        'attachments' => adSpentFiles(),
    ]));
    $request = FundRequest::sole();

    $this->actingAs($this->user)
        ->put("{$this->url}/{$request->id}", requirementsPayload($this->adSpent, ['particulars' => [['name' => 'Item', 'quantity' => 1, 'unit_price' => 800]]]))
        ->assertSessionHasNoErrors();

    expect(requestMedia($request)->pluck('file_name')->sort()->values()->all())->toBe(['statement.pdf', 'tracker.pdf'])
        ->and((float) $request->fresh()->amount_requested)->toBe(800.0);
});

test('switching the type drops the old type\'s files and ticks', function () {
    $this->actingAs($this->user)->post($this->url, requirementsPayload($this->adSpent, [
        'checklist_ids' => [$this->budget->id],
        'attachments' => adSpentFiles(),
    ]));
    $request = FundRequest::sole();
    $paths = requestMedia($request)->map->getPathRelativeToRoot();

    $this->actingAs($this->user)
        ->post("{$this->url}/{$request->id}", requirementsPayload($this->salary, [
            '_method' => 'put',
            'attachments' => [$this->payslip->id => UploadedFile::fake()->create('payslip.pdf', 10, 'application/pdf')],
        ]))
        ->assertSessionHasNoErrors();

    expect(requestMedia($request)->pluck('file_name')->all())->toBe(['payslip.pdf'])
        ->and($request->checkedChecklists()->count())->toBe(0);
    $paths->each(fn ($path) => Storage::disk($this->disk)->assertMissing($path));
});

test('the edit page carries the saved files and ticked checklist', function () {
    $this->actingAs($this->user)->post($this->url, requirementsPayload($this->adSpent, [
        'checklist_ids' => [$this->budget->id],
        'attachments' => adSpentFiles(),
    ]));
    $request = FundRequest::sole();

    $this->actingAs($this->user)
        ->get("{$this->url}/{$request->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('workspaces/finance/request-funds/edit')
            ->where('requestFund.checklist_ids', [$this->budget->id])
            ->has('requestFund.files', 2)
            ->where('requestFund.files.0.attachment_requirement_id', $this->statement->id)
            ->where('requestFund.files.0.file_name', 'statement.pdf'));
});

test('downloads a file only through the request it belongs to', function () {
    foreach (['a-', 'b-'] as $prefix) {
        $this->actingAs($this->user)->post($this->url, requirementsPayload($this->adSpent, [
            'attachments' => adSpentFiles($prefix),
        ]));
    }
    [$first, $second] = FundRequest::orderBy('id')->get()->all();
    $attachment = $first->attachments()->first();

    // The bucket is private: the file is handed out as a short-lived signed URL.
    $this->actingAs($this->user)->get("{$this->url}/{$first->id}/attachments/{$attachment->id}")->assertRedirectContains($attachment->file()->file_name);
    $this->actingAs($this->user)->get("{$this->url}/{$second->id}/attachments/{$attachment->id}")->assertNotFound();
});

test('deleting a request deletes its files', function () {
    $this->actingAs($this->user)->post($this->url, requirementsPayload($this->adSpent, [
        'attachments' => adSpentFiles(),
    ]));
    $request = FundRequest::sole();
    $paths = requestMedia($request)->map->getPathRelativeToRoot();

    $this->actingAs($this->user)->delete("{$this->url}/{$request->id}")->assertRedirect();

    $paths->each(fn ($path) => Storage::disk($this->disk)->assertMissing($path));
});

test('one requirement can be called for by several types', function () {
    $this->salary->attachments()->attach($this->statement);
    $this->salary->checklists()->attach($this->budget);

    $this->actingAs($this->user)
        ->post($this->url, requirementsPayload($this->salary, [
            'checklist_ids' => [$this->budget->id, $this->approved->id],
            'attachments' => [
                $this->payslip->id => UploadedFile::fake()->create('payslip.pdf', 10, 'application/pdf'),
                $this->statement->id => UploadedFile::fake()->create('statement.pdf', 10, 'application/pdf'),
            ],
        ]))
        ->assertSessionHasNoErrors();

    $request = FundRequest::sole();
    expect($request->attachments()->pluck('attachment_requirement_id')->sort()->values()->all())->toBe([$this->statement->id, $this->payslip->id])
        ->and($request->checkedChecklists()->pluck('name')->sort()->values()->all())->toBe(['Approved', 'Budget']);
});
