<?php

use App\Models\User;
use App\Models\Workspace;
use Modules\Finance\Models\FundRequestAttachment;
use Modules\Finance\Models\FundRequestChecklist;
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
    'attachments' => ['attachments', FundRequestAttachment::class],
    'checklists' => ['checklists', FundRequestChecklist::class],
]);

test('lists each transaction type with its own attachments and checklist', function () {
    $salary = TransactionType::create(['workspace_id' => $this->workspace->id, 'name' => 'Salary', 'nature' => 'debit']);
    $this->type->attachments()->create(['workspace_id' => $this->workspace->id, 'name' => 'Receipt']);
    $this->type->checklists()->create(['workspace_id' => $this->workspace->id, 'name' => 'Budget approved']);
    $salary->attachments()->create(['workspace_id' => $this->workspace->id, 'name' => 'Payslip']);

    $this->actingAs($this->user)
        ->get($this->url)
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('workspaces/finance/management/index')
            ->where('types.0.name', 'Adspent')
            ->where('types.0.attachments.0.name', 'Receipt')
            ->where('types.0.checklists.0.name', 'Budget approved')
            ->where('types.1.name', 'Salary')
            ->where('types.1.attachments.0.name', 'Payslip')
            ->has('types.1.checklists', 0));
});

test('adds, renames and deletes an item under a type', function (string $kind, string $model) {
    $url = "{$this->url}/transaction-types/{$this->type->id}/{$kind}";

    $this->actingAs($this->user)->post($url, ['name' => '  Receipt  '])->assertRedirect();
    $item = $model::sole();
    expect($item->name)->toBe('Receipt')
        ->and($item->transaction_type_id)->toBe($this->type->id)
        ->and($item->workspace_id)->toBe($this->workspace->id);

    $this->actingAs($this->user)->put("{$url}/{$item->id}", ['name' => 'Invoice'])->assertRedirect();
    expect($item->fresh()->name)->toBe('Invoice');

    $this->actingAs($this->user)->delete("{$url}/{$item->id}")->assertRedirect();
    expect($model::count())->toBe(0);
})->with('kinds');

test('the same name may repeat across types but not within one', function (string $kind, string $model) {
    $salary = TransactionType::create(['workspace_id' => $this->workspace->id, 'name' => 'Salary', 'nature' => 'debit']);
    $this->type->{$kind}()->create(['workspace_id' => $this->workspace->id, 'name' => 'Receipt']);

    $this->actingAs($this->user)
        ->post("{$this->url}/transaction-types/{$this->type->id}/{$kind}", ['name' => 'Receipt'])
        ->assertSessionHasErrors('name');

    $this->actingAs($this->user)
        ->post("{$this->url}/transaction-types/{$salary->id}/{$kind}", ['name' => 'Receipt'])
        ->assertSessionHasNoErrors();
    expect($model::count())->toBe(2);
})->with('kinds');

test('cannot reach an item through a different type', function (string $kind) {
    $salary = TransactionType::create(['workspace_id' => $this->workspace->id, 'name' => 'Salary', 'nature' => 'debit']);
    $item = $salary->{$kind}()->create(['workspace_id' => $this->workspace->id, 'name' => 'Payslip']);
    $url = "{$this->url}/transaction-types/{$this->type->id}/{$kind}/{$item->id}";

    $this->actingAs($this->user)->put($url, ['name' => 'Moved'])->assertNotFound();
    $this->actingAs($this->user)->delete($url)->assertNotFound();
    expect($item->fresh()->name)->toBe('Payslip');
})->with('kinds');

test('cannot add to another workspace\'s transaction type', function (string $kind, string $model) {
    $other = Workspace::factory()->create(['finance_module_enabled' => true]);
    $foreignType = TransactionType::create(['workspace_id' => $other->id, 'name' => 'Theirs', 'nature' => 'debit']);

    $this->actingAs($this->user)
        ->post("{$this->url}/transaction-types/{$foreignType->id}/{$kind}", ['name' => 'Receipt'])
        ->assertNotFound();
    expect($model::count())->toBe(0);
})->with('kinds');

test('deleting a type deletes its attachments and checklist', function () {
    $this->type->attachments()->create(['workspace_id' => $this->workspace->id, 'name' => 'Receipt']);
    $this->type->checklists()->create(['workspace_id' => $this->workspace->id, 'name' => 'Budget approved']);

    $this->type->delete();

    expect(FundRequestAttachment::count())->toBe(0)
        ->and(FundRequestChecklist::count())->toBe(0);
});

test('404s when the finance module is off', function () {
    $this->workspace->update(['finance_module_enabled' => false]);

    $this->actingAs($this->user)->get($this->url)->assertNotFound();
});
