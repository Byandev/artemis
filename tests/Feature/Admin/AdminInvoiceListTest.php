<?php

use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function adminInvoice(Workspace $workspace, array $attributes = []): Invoice
{
    static $sequence = 0;
    $sequence++;

    return Invoice::create(array_merge([
        'number' => sprintf('INV-2026-%06d', $sequence),
        'workspace_id' => $workspace->id,
        'bill_to_name' => 'Acme Corp',
        'issue_date' => Carbon::today()->subWeek()->toDateString(),
        'due_date' => Carbon::today()->toDateString(),
        'currency' => 'PHP',
        'line_items' => [['description' => 'Plan', 'quantity' => 1, 'unit_price' => 2500, 'amount' => 2500]],
        'subtotal' => 2500,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 2500,
        'status' => Invoice::STATUS_SENT,
    ], $attributes));
}

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
    $this->workspace = Workspace::factory()->create();
    $this->url = route('admin.invoices.index');
});

it('honours the per_page parameter', function () {
    foreach (range(1, 7) as $i) {
        adminInvoice($this->workspace);
    }

    $this->actingAs($this->admin)
        ->get($this->url.'?per_page=3')
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoices.per_page', 3)
            ->where('invoices.total', 7)
            ->where('invoices.last_page', 3)
            ->count('invoices.data', 3)
        );
});

it('sorts by amount ascending and descending', function () {
    adminInvoice($this->workspace, ['total' => 500]);
    adminInvoice($this->workspace, ['total' => 9000]);

    $this->actingAs($this->admin)
        ->get($this->url.'?sort=total')
        ->assertInertia(fn (Assert $page) => $page->where('invoices.data.0.total', '500.00'));

    $this->actingAs($this->admin)
        ->get($this->url.'?sort=-total')
        ->assertInertia(fn (Assert $page) => $page->where('invoices.data.0.total', '9000.00'));
});

it('defaults to newest issue date first', function () {
    adminInvoice($this->workspace, ['issue_date' => '2026-01-01']);
    $newest = adminInvoice($this->workspace, ['issue_date' => '2026-08-01']);

    $this->actingAs($this->admin)
        ->get($this->url)
        ->assertInertia(fn (Assert $page) => $page->where('invoices.data.0.number', $newest->number));
});

it('keeps the status filter applied while sorting', function () {
    adminInvoice($this->workspace, ['total' => 100, 'status' => Invoice::STATUS_PAID]);
    adminInvoice($this->workspace, ['total' => 200, 'status' => Invoice::STATUS_SENT]);
    adminInvoice($this->workspace, ['total' => 300, 'status' => Invoice::STATUS_PAID]);

    $this->actingAs($this->admin)
        ->get($this->url.'?status=paid&sort=-total')
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoices.total', 2)
            ->where('invoices.data.0.total', '300.00')
        );
});

it('searches across number, bill-to and workspace name', function () {
    $other = Workspace::factory()->create(['name' => 'Northwind Workspace']);
    adminInvoice($other);
    adminInvoice($this->workspace, ['bill_to_name' => 'Acme Corp']);

    $this->actingAs($this->admin)
        ->get($this->url.'?search=Northwind')
        ->assertInertia(fn (Assert $page) => $page->where('invoices.total', 1));
});

it('echoes the applied sort back to the page', function () {
    adminInvoice($this->workspace);

    $this->actingAs($this->admin)
        ->get($this->url.'?sort=-total')
        ->assertInertia(fn (Assert $page) => $page->where('filters.sort', '-total'));
});
