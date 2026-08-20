<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/** A workspace member whose role carries exactly $permissions. */
function invoiceMemberWithPermissions(Workspace $workspace, array $permissions): User
{
    $user = User::factory()->create();

    $role = Role::create([
        'workspace_id' => $workspace->id,
        'name' => 'Role '.uniqid(),
    ]);

    foreach ($permissions as $name) {
        $permission = Permission::firstOrCreate(['name' => $name], ['category' => 'Billing']);
        DB::table('role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

function workspaceInvoice(Workspace $workspace, array $attributes = []): Invoice
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
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->owner->id,
        'billing_module_enabled' => true,
    ]);
    $this->url = route('workspaces.billing.invoices.index', ['workspace' => $this->workspace->slug]);
});

it('lists only the invoices belonging to this workspace', function () {
    $mine = workspaceInvoice($this->workspace);

    $other = Workspace::factory()->create(['billing_module_enabled' => true]);
    workspaceInvoice($other, ['bill_to_name' => 'Someone Else']);

    $this->actingAs($this->owner)
        ->get($this->url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/billing/invoices')
            ->where('invoices.total', 1)
            ->where('invoices.data.0.number', $mine->number)
        );
});

it('shows every invoice for the workspace regardless of status', function () {
    workspaceInvoice($this->workspace, ['status' => Invoice::STATUS_DRAFT]);
    workspaceInvoice($this->workspace, ['status' => Invoice::STATUS_SENT]);
    workspaceInvoice($this->workspace, ['status' => Invoice::STATUS_PAID]);

    $this->actingAs($this->owner)
        ->get($this->url)
        ->assertInertia(fn (Assert $page) => $page->where('invoices.total', 3));
});

it('lets a member with the permission view the invoices', function () {
    workspaceInvoice($this->workspace);

    $user = invoiceMemberWithPermissions($this->workspace, [
        PermissionEnum::ViewInvoices->value,
    ]);

    $this->actingAs($user)->get($this->url)->assertOk();
});

it('forbids a member without the permission', function () {
    $user = invoiceMemberWithPermissions($this->workspace, []);

    $this->actingAs($user)->get($this->url)->assertForbidden();
});

it('forbids someone who is not in the workspace at all', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get($this->url)->assertForbidden();
});

it('404s when the billing module is switched off', function () {
    $this->workspace->update(['billing_module_enabled' => false]);

    $this->actingAs($this->owner)->get($this->url)->assertNotFound();
});



it('filters by status', function () {
    workspaceInvoice($this->workspace, ['status' => Invoice::STATUS_PAID]);
    workspaceInvoice($this->workspace, ['status' => Invoice::STATUS_SENT]);

    $this->actingAs($this->owner)
        ->get($this->url.'?status=paid')
        ->assertInertia(fn (Assert $page) => $page->where('invoices.total', 1));
});

it('searches by invoice number', function () {
    $target = workspaceInvoice($this->workspace);
    workspaceInvoice($this->workspace);

    $this->actingAs($this->owner)
        ->get($this->url.'?search='.$target->number)
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoices.total', 1)
            ->where('invoices.data.0.number', $target->number)
        );
});

it('downloads the pdf for its own invoice', function () {
    $invoice = workspaceInvoice($this->workspace);

    $this->actingAs($this->owner)
        ->get(route('workspaces.billing.invoices.download', [
            'workspace' => $this->workspace->slug,
            'invoice' => $invoice->id,
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('will not download another workspace invoice through this workspace', function () {
    $other = Workspace::factory()->create(['billing_module_enabled' => true]);
    $foreign = workspaceInvoice($other);

    // Binding resolves the invoice by id alone, so this is the check that stops
    // a member reading another client's billing by guessing an id.
    $this->actingAs($this->owner)
        ->get(route('workspaces.billing.invoices.download', [
            'workspace' => $this->workspace->slug,
            'invoice' => $foreign->id,
        ]))
        ->assertNotFound();
});

it('hides billing permissions from a workspace with the module off', function () {
    $this->workspace->update(['billing_module_enabled' => false]);

    expect($this->workspace->fresh()->disabledPermissionCategories())->toContain('Billing');
});

it('honours the per_page parameter', function () {
    foreach (range(1, 7) as $i) {
        workspaceInvoice($this->workspace);
    }

    $this->actingAs($this->owner)
        ->get($this->url.'?per_page=3')
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoices.per_page', 3)
            ->where('invoices.total', 7)
            ->where('invoices.last_page', 3)
            ->count('invoices.data', 3)
        );
});

it('pages through with per_page applied', function () {
    foreach (range(1, 7) as $i) {
        workspaceInvoice($this->workspace);
    }

    $this->actingAs($this->owner)
        ->get($this->url.'?per_page=3&page=3')
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoices.current_page', 3)
            ->count('invoices.data', 1)
        );
});

it('sorts by amount ascending and descending', function () {
    workspaceInvoice($this->workspace, ['total' => 500]);
    workspaceInvoice($this->workspace, ['total' => 9000]);
    workspaceInvoice($this->workspace, ['total' => 3000]);

    $this->actingAs($this->owner)
        ->get($this->url.'?sort=total')
        ->assertInertia(fn (Assert $page) => $page->where('invoices.data.0.total', '500.00'));

    $this->actingAs($this->owner)
        ->get($this->url.'?sort=-total')
        ->assertInertia(fn (Assert $page) => $page->where('invoices.data.0.total', '9000.00'));
});

it('sorts by issue date', function () {
    $old = workspaceInvoice($this->workspace, ['issue_date' => '2026-01-01']);
    $new = workspaceInvoice($this->workspace, ['issue_date' => '2026-08-01']);

    $this->actingAs($this->owner)
        ->get($this->url.'?sort=issue_date')
        ->assertInertia(fn (Assert $page) => $page->where('invoices.data.0.number', $old->number));

    $this->actingAs($this->owner)
        ->get($this->url.'?sort=-issue_date')
        ->assertInertia(fn (Assert $page) => $page->where('invoices.data.0.number', $new->number));
});

it('defaults to newest issue date first', function () {
    workspaceInvoice($this->workspace, ['issue_date' => '2026-01-01']);
    $newest = workspaceInvoice($this->workspace, ['issue_date' => '2026-08-01']);

    $this->actingAs($this->owner)
        ->get($this->url)
        ->assertInertia(fn (Assert $page) => $page->where('invoices.data.0.number', $newest->number));
});

it('ignores a sort on a column that is not allowed', function () {
    workspaceInvoice($this->workspace);

    // Spatie throws on a disallowed sort unless it is simply not applied;
    // this pins that a probe like ?sort=bill_to_email cannot leak an ordering.
    $this->actingAs($this->owner)
        ->get($this->url.'?sort=bill_to_email')
        ->assertStatus(400);
});

it('keeps the filter applied while sorting', function () {
    workspaceInvoice($this->workspace, ['total' => 100, 'status' => Invoice::STATUS_PAID]);
    workspaceInvoice($this->workspace, ['total' => 200, 'status' => Invoice::STATUS_SENT]);
    workspaceInvoice($this->workspace, ['total' => 300, 'status' => Invoice::STATUS_PAID]);

    $this->actingAs($this->owner)
        ->get($this->url.'?status=paid&sort=-total')
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoices.total', 2)
            ->where('invoices.data.0.total', '300.00')
        );
});

it('searches by bill-to name as well as number', function () {
    workspaceInvoice($this->workspace, ['bill_to_name' => 'Northwind Trading']);
    workspaceInvoice($this->workspace, ['bill_to_name' => 'Acme Corp']);

    $this->actingAs($this->owner)
        ->get($this->url.'?search=Northwind')
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoices.total', 1)
            ->where('invoices.data.0.bill_to_name', 'Northwind Trading')
        );
});

it('echoes the applied sort back so the header can render its arrow', function () {
    workspaceInvoice($this->workspace);

    $this->actingAs($this->owner)
        ->get($this->url.'?sort=-total')
        ->assertInertia(fn (Assert $page) => $page->where('filters.sort', '-total'));
});
