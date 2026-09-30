<?php

use App\Models\User;
use App\Models\Workspace;
use Modules\Finance\Models\FundRequestAttachmentRequirement;
use Modules\Finance\Models\FundRequestChecklistRequirement;
use Modules\Finance\Models\TransactionType;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'finance_module_enabled' => true,
    ]);
    $this->type = TransactionType::create(['workspace_id' => $this->workspace->id, 'name' => 'Adspent', 'nature' => 'debit']);
    $this->url = "/workspaces/{$this->workspace->slug}/finance/management";
});

dataset('kinds', [
    'attachments' => ['attachments', FundRequestAttachmentRequirement::class],
    'checklists' => ['checklists', FundRequestChecklistRequirement::class],
]);

test('lists the workspace\'s requirements', function () {
    $salary = TransactionType::create(['workspace_id' => $this->workspace->id, 'name' => 'Salary', 'nature' => 'debit']);
    $receipt = $this->type->attachments()->create(['workspace_id' => $this->workspace->id, 'name' => 'Receipt']);
    $salary->attachments()->attach($receipt);
    $this->type->checklists()->create(['workspace_id' => $this->workspace->id, 'name' => 'Budget approved']);
    FundRequestAttachmentRequirement::create(['workspace_id' => $this->workspace->id, 'name' => 'Unused']);

    $this->actingAs($this->user)
        ->get($this->url)
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('workspaces/finance/management/index')
            ->where('requirements.attachments.0.name', 'Receipt')
            ->where('requirements.attachments.0.transaction_types_count', 2)
            ->where('requirements.attachments.1.name', 'Unused')
            ->where('requirements.attachments.1.transaction_types_count', 0)
            ->has('requirements.checklists', 1));
});

test('sorts the requirements by name, A→Z by default and Z→A on -name', function (string $kind, string $class) {
    foreach (['Beta', 'alpha', 'Gamma'] as $name) {
        $class::create(['workspace_id' => $this->workspace->id, 'name' => $name]);
    }

    $this->actingAs($this->user)
        ->get($this->url)
        ->assertInertia(fn ($p) => $p
            ->where("requirements.{$kind}.0.name", 'alpha')
            ->where("requirements.{$kind}.2.name", 'Gamma'));

    $this->actingAs($this->user)
        ->get("{$this->url}?sort=-name")
        ->assertInertia(fn ($p) => $p
            ->where('query.sort', '-name')
            ->where("requirements.{$kind}.0.name", 'Gamma')
            ->where("requirements.{$kind}.2.name", 'alpha'));
})->with('kinds');

test('the transaction types page carries each type\'s requirements and the workspace\'s, for Manage Requirements', function () {
    $receipt = $this->type->attachments()->create(['workspace_id' => $this->workspace->id, 'name' => 'Receipt']);
    FundRequestChecklistRequirement::create(['workspace_id' => $this->workspace->id, 'name' => 'Budget approved']);

    $this->actingAs($this->user)
        ->get("/workspaces/{$this->workspace->slug}/finance/transaction-types")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('workspaces/finance/transaction-types/index')
            ->where('types.data.0.attachments.0.id', $receipt->id)
            ->has('types.data.0.checklists', 0)
            ->where('requirements.attachments.0.name', 'Receipt')
            ->where('requirements.attachments.0.transaction_types_count', 1)
            ->where('requirements.checklists.0.name', 'Budget approved'));
});

test('adds a requirement linked to the type it was added from, renames and deletes it', function (string $kind, string $model) {
    $this->actingAs($this->user)
        ->post("{$this->url}/{$kind}", ['name' => '  Receipt  ', 'transaction_type_id' => $this->type->id])
        ->assertRedirect();
    $item = $model::sole();
    expect($item->name)->toBe('Receipt')
        ->and($item->workspace_id)->toBe($this->workspace->id)
        ->and($this->type->{$kind}()->pluck('id')->all())->toBe([$item->id]);

    $this->actingAs($this->user)->put("{$this->url}/{$kind}/{$item->id}", ['name' => 'Invoice'])->assertRedirect();
    expect($item->fresh()->name)->toBe('Invoice');

    $this->actingAs($this->user)->delete("{$this->url}/{$kind}/{$item->id}")->assertRedirect();
    expect($model::count())->toBe(0)
        ->and($this->type->{$kind}()->count())->toBe(0);
})->with('kinds');

test('adds a requirement without linking it to a type', function (string $kind, string $model) {
    $this->actingAs($this->user)->post("{$this->url}/{$kind}", ['name' => 'Receipt'])->assertSessionHasNoErrors();

    expect($model::sole()->transactionTypes()->count())->toBe(0);
})->with('kinds');

test('links one requirement to several types and unlinks it from one', function (string $kind, string $model) {
    $salary = TransactionType::create(['workspace_id' => $this->workspace->id, 'name' => 'Salary', 'nature' => 'debit']);
    $item = $model::create(['workspace_id' => $this->workspace->id, 'name' => 'Receipt']);

    foreach ([$this->type, $salary] as $type) {
        $this->actingAs($this->user)->put("{$this->url}/transaction-types/{$type->id}/{$kind}/{$item->id}")->assertRedirect();
    }
    // Linking twice is a no-op, not a duplicate-key error.
    $this->actingAs($this->user)->put("{$this->url}/transaction-types/{$salary->id}/{$kind}/{$item->id}")->assertRedirect();
    expect($item->transactionTypes()->pluck('name')->sort()->values()->all())->toBe(['Adspent', 'Salary']);

    $this->actingAs($this->user)->delete("{$this->url}/transaction-types/{$this->type->id}/{$kind}/{$item->id}")->assertRedirect();
    expect($item->transactionTypes()->pluck('name')->all())->toBe(['Salary'])
        ->and($item->fresh())->not->toBeNull();
})->with('kinds');

test('saves every requirement a type calls for in one go', function () {
    $receipt = FundRequestAttachmentRequirement::create(['workspace_id' => $this->workspace->id, 'name' => 'Receipt']);
    $invoice = FundRequestAttachmentRequirement::create(['workspace_id' => $this->workspace->id, 'name' => 'Invoice']);
    $budget = FundRequestChecklistRequirement::create(['workspace_id' => $this->workspace->id, 'name' => 'Budget approved']);
    $this->type->attachments()->attach($receipt);
    $this->type->checklists()->attach($budget);

    $this->actingAs($this->user)
        ->put("{$this->url}/transaction-types/{$this->type->id}", ['attachments' => [$invoice->id], 'checklists' => []])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($this->type->attachments()->pluck('name')->all())->toBe(['Invoice'])
        ->and($this->type->checklists()->count())->toBe(0);
});

test('saving a type\'s requirements rejects another workspace\'s', function () {
    $other = Workspace::factory()->create(['finance_module_enabled' => true]);
    $foreign = FundRequestAttachmentRequirement::create(['workspace_id' => $other->id, 'name' => 'Theirs']);
    $foreignType = TransactionType::create(['workspace_id' => $other->id, 'name' => 'Theirs', 'nature' => 'debit']);

    $this->actingAs($this->user)
        ->put("{$this->url}/transaction-types/{$this->type->id}", ['attachments' => [$foreign->id], 'checklists' => []])
        ->assertSessionHasErrors('attachments.0');
    $this->actingAs($this->user)
        ->put("{$this->url}/transaction-types/{$foreignType->id}", ['attachments' => [], 'checklists' => []])
        ->assertNotFound();

    expect($this->type->attachments()->count())->toBe(0);
});

test('a name is unique within the workspace, not across workspaces', function (string $kind, string $model) {
    $other = Workspace::factory()->create(['finance_module_enabled' => true]);
    $model::create(['workspace_id' => $other->id, 'name' => 'Receipt']);

    $this->actingAs($this->user)->post("{$this->url}/{$kind}", ['name' => 'Receipt'])->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post("{$this->url}/{$kind}", ['name' => 'Receipt'])->assertSessionHasErrors('name');

    expect($model::count())->toBe(2);
})->with('kinds');

test('cannot touch another workspace\'s requirement or type', function (string $kind, string $model) {
    $other = Workspace::factory()->create(['finance_module_enabled' => true]);
    $foreignType = TransactionType::create(['workspace_id' => $other->id, 'name' => 'Theirs', 'nature' => 'debit']);
    $foreignItem = $model::create(['workspace_id' => $other->id, 'name' => 'Theirs']);
    $ownItem = $model::create(['workspace_id' => $this->workspace->id, 'name' => 'Receipt']);

    $this->actingAs($this->user)->put("{$this->url}/{$kind}/{$foreignItem->id}", ['name' => 'Mine'])->assertNotFound();
    $this->actingAs($this->user)->delete("{$this->url}/{$kind}/{$foreignItem->id}")->assertNotFound();
    $this->actingAs($this->user)->put("{$this->url}/transaction-types/{$this->type->id}/{$kind}/{$foreignItem->id}")->assertNotFound();
    $this->actingAs($this->user)->put("{$this->url}/transaction-types/{$foreignType->id}/{$kind}/{$ownItem->id}")->assertNotFound();
    $this->actingAs($this->user)
        ->post("{$this->url}/{$kind}", ['name' => 'New', 'transaction_type_id' => $foreignType->id])
        ->assertSessionHasErrors('transaction_type_id');

    expect($foreignItem->fresh()->name)->toBe('Theirs')
        ->and($model::count())->toBe(2);
})->with('kinds');

test('deleting a type unlinks its requirements but keeps them', function () {
    $this->type->attachments()->create(['workspace_id' => $this->workspace->id, 'name' => 'Receipt']);
    $this->type->checklists()->create(['workspace_id' => $this->workspace->id, 'name' => 'Budget approved']);

    $this->type->delete();

    expect(FundRequestAttachmentRequirement::sole()->transactionTypes()->count())->toBe(0)
        ->and(FundRequestChecklistRequirement::sole()->transactionTypes()->count())->toBe(0);
});

test('404s when the finance module is off', function () {
    $this->workspace->update(['finance_module_enabled' => false]);

    $this->actingAs($this->user)->get($this->url)->assertNotFound();
});

test('a type is fund-requestable only when marked so, and can be switched off', function () {
    $url = "/workspaces/{$this->workspace->slug}/finance/transaction-types";

    $this->actingAs($this->user)
        ->post($url, ['name' => 'Salary', 'nature' => 'debit', 'fund_requestable' => true])
        ->assertRedirect();
    $this->actingAs($this->user)
        ->post($url, ['name' => 'Sales', 'nature' => 'credit'])
        ->assertRedirect();

    $salary = TransactionType::where('name', 'Salary')->sole();
    expect($salary->fund_requestable)->toBeTrue()
        ->and(TransactionType::where('name', 'Sales')->sole()->fund_requestable)->toBeFalse();

    $this->actingAs($this->user)
        ->put("{$url}/{$salary->id}", ['name' => 'Salary', 'nature' => 'debit', 'fund_requestable' => false])
        ->assertRedirect();

    expect($salary->fresh()->fund_requestable)->toBeFalse();
});
