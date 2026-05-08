<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\RemittanceRequest;
use Modules\Finance\Models\Remittance;
use Modules\Finance\Models\RemittanceItem;
use Modules\Finance\Models\Transaction;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RemittanceController extends Controller
{
    use AuthorizesRequests;

    protected function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    protected function ensureOwns(Workspace $workspace, Remittance $remittance): void
    {
        if ($remittance->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    protected function validateTransactionFor(Workspace $workspace, ?int $transactionId): void
    {
        if (! $transactionId) {
            return;
        }
        $ok = Transaction::where('id', $transactionId)
            ->where('workspace_id', $workspace->id)->exists();
        if (! $ok) {
            throw ValidationException::withMessages(['transaction_id' => 'Invalid transaction for this workspace.']);
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceRemittances->value, $workspace);

        $remittances = QueryBuilder::for(
            Remittance::where('workspace_id', $workspace->id)->with('transaction.account')
        )
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where('soa_number', 'like', "%{$v}%")->orWhere('courier', 'like', "%{$v}%")),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('unreconciled', function ($q, $v) {
                    if ((bool) $v) {
                        $q->whereNull('transaction_id');
                    }
                }),
                AllowedFilter::callback('date_from', fn ($q, $v) => $q->whereDate('billing_date_from', '>=', $v)),
                AllowedFilter::callback('date_to', fn ($q, $v) => $q->whereDate('billing_date_to', '<=', $v)),
            ])
            ->allowedSorts(['id', 'billing_date_from', 'billing_date_to', 'courier', 'soa_number', 'gross_cod', 'net_amount', 'status', 'created_at'])
            ->defaultSort('-billing_date_to', '-created_at')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        $remittances->through(function (Remittance $r) {
            $r->is_reconciled = $r->transaction_id !== null;

            return $r;
        });

        $unreconciledCount = Remittance::where('workspace_id', $workspace->id)
            ->whereNull('transaction_id')->count();

        return Inertia::render('workspaces/finance/remittances/index', [
            'workspace' => $workspace,
            'remittances' => $remittances,
            'unreconciledCount' => $unreconciledCount,
            'transactions' => Transaction::where('workspace_id', $workspace->id)
                ->where('transaction_type', 'remittance')
                ->with('account')
                ->orderByDesc('date')
                ->limit(200)
                ->get(['id', 'account_id', 'date', 'description', 'amount', 'type']),
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(RemittanceRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceRemittances->value, $workspace);
        $data = $request->validated();
        $this->validateTransactionFor($workspace, $data['transaction_id'] ?? null);

        Remittance::create([...$data, 'workspace_id' => $workspace->id]);

        return redirect()->route('workspaces.finance.remittances.index', $workspace->slug)
            ->with('success', 'Remittance created.');
    }

    public function show(Request $request, Workspace $workspace, Remittance $remittance)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceRemittances->value, $workspace);
        $this->ensureOwns($workspace, $remittance);

        $remittance->load('transaction.account');

        $perPage = (int) $request->input('perPage', 15);
        $perPage = in_array($perPage, [15, 25, 50, 100]) ? $perPage : 15;

        $items = QueryBuilder::for(
            RemittanceItem::where('remittance_id', $remittance->id)
        )
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where(function ($q) use ($v) {
                    $q->where('waybill_number', 'like', "%{$v}%")
                        ->orWhere('order_number', 'like', "%{$v}%")
                        ->orWhere('sender_city', 'like', "%{$v}%")
                        ->orWhere('destination_city', 'like', "%{$v}%");
                })),
            ])
            ->allowedSorts(['waybill_number', 'order_number', 'shipping_date', 'sender_city', 'destination_city', 'item_value', 'total_shipping_cost', 'cod', 'cod_commission', 'signing_time'])
            ->defaultSort('-shipping_date')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('workspaces/finance/remittances/show', [
            'workspace' => $workspace,
            'remittance' => [
                ...$remittance->toArray(),
                'is_reconciled' => $remittance->transaction_id !== null,
            ],
            'items' => $items,
            'itemsQuery' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'transactions' => Transaction::where('workspace_id', $workspace->id)
                ->where('transaction_type', 'remittance')
                ->with('account')
                ->orderByDesc('date')
                ->limit(200)
                ->get(['id', 'account_id', 'date', 'description', 'amount', 'type']),
        ]);
    }

    public function update(RemittanceRequest $request, Workspace $workspace, Remittance $remittance)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceRemittances->value, $workspace);
        $this->ensureOwns($workspace, $remittance);
        $data = $request->validated();
        $this->validateTransactionFor($workspace, $data['transaction_id'] ?? null);

        $remittance->update($data);

        return redirect()->back()->with('success', 'Remittance updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Remittance $remittance)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::DeleteFinanceRemittances->value, $workspace);
        $this->ensureOwns($workspace, $remittance);

        $remittance->delete();

        return redirect()->route('workspaces.finance.remittances.index', $workspace->slug)
            ->with('success', 'Remittance deleted.');
    }

    public function import(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceRemittances->value, $workspace);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray();

        if (count($rows) < 2) {
            return redirect()->back()->withErrors(['file' => 'The file contains no data rows.']);
        }

        $header = array_map(fn ($h) => trim((string) $h), $rows[0]);
        $colMap = array_flip($header);

        $required = ['Bill number', 'Billing Date', 'COD Total Amount', 'COD commission', 'COD commission VAT fee', 'Settled shipping fee', 'Return Shipping', 'Payment amount'];
        $missing = array_filter($required, fn ($col) => ! isset($colMap[$col]));
        if ($missing) {
            return redirect()->back()->withErrors(['file' => 'Missing columns: '.implode(', ', $missing)]);
        }

        $imported = 0;
        $skipped = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $soaNumber = trim((string) ($row[$colMap['Bill number']] ?? ''));
            if (! $soaNumber) {
                continue;
            }

            // Skip duplicates within this workspace
            if (Remittance::where('workspace_id', $workspace->id)->where('soa_number', $soaNumber)->exists()) {
                $skipped++;

                continue;
            }

            // Parse billing date range (format: "2026-03-24+2026-03-24")
            $billingDateRaw = trim((string) ($row[$colMap['Billing Date']] ?? ''));
            $dateParts = explode('+', $billingDateRaw);
            $dateFrom = $dateParts[0] ?? null;
            $dateTo = $dateParts[1] ?? $dateParts[0] ?? null;

            if (! $dateFrom || ! strtotime($dateFrom)) {
                continue;
            }

            $parseNumber = fn ($v) => (float) str_replace(',', '', trim((string) $v));
            $col = fn (string $name) => isset($colMap[$name]) ? trim((string) ($row[$colMap[$name]] ?? '')) : '';
            $colNum = fn (string $name) => $parseNumber($col($name));
            $colDate = fn (string $name) => ($d = $col($name)) && strtotime($d) ? $d : null;

            Remittance::create([
                'workspace_id' => $workspace->id,
                'courier' => 'J&T',
                'soa_number' => $soaNumber,
                'billing_date_from' => $dateFrom,
                'billing_date_to' => $dateTo,
                // Client info
                'vip_code' => $col('VIP Code') ?: null,
                'client_name' => $col("Client'S Name") ?: null,
                'settlement_method' => $col('Settlement Method') ?: null,
                'service_management' => $col('Service Management') ?: null,
                'affiliated_branch' => $col("Customer's affiliated branch") ?: null,
                // COD details
                'cod_settlement_category' => $col('COD settlement category') ?: null,
                'cod_flag' => $col('COD Flag') ?: null,
                'cod_accumulated_amount' => $colNum('COD accumulated amount'),
                'gross_cod' => $colNum('COD Total Amount'),
                'cod_amount_cwt' => $colNum('COD amount cwt'),
                'cod_commission_rate' => $colNum('COD commission rate'),
                'cod_fee' => $colNum('COD commission'),
                'cod_fee_vat' => $colNum('COD commission VAT fee'),
                'cod_cwt' => $colNum('CODCWT'),
                'total_cod_payable' => $colNum('Total COD payable'),
                // Bank info
                'opening_bank' => $col('Opening Bank') ?: null,
                'bank_account' => $col('Bank account') ?: null,
                'payee' => $col('Payee') ?: null,
                // Freight
                'total_freight_receivable' => $colNum('Total freight receivable'),
                'shipping_fee' => $colNum('Settled shipping fee'),
                'shipping_fee_cwt' => $colNum('ShippingFee CWT'),
                'return_shipping' => $colNum('Return Shipping'),
                'return_cwt' => $colNum('Return CWT'),
                'super_value_added_fee' => $colNum('Super Value-added fee'),
                // Adjustments
                'return_freight_policy_adjustment' => $colNum('Return freight policy adjustment'),
                'cod_amount_adjustment' => $colNum('COD Amount Adjustment'),
                'cod_commission_adjustment' => $colNum('COD commission adjustment'),
                'cod_vat_adjustment' => $colNum('COD VAT adjustment'),
                'cod_cwt_adjustment' => $colNum('CODCWT adjustment'),
                'total_shipping_fee_adjustment' => $colNum('Total ShippingFee Adjustment'),
                'total_shipping_fee_cwt_adjustment' => $colNum('Total ShippingFee CWT Adjustment'),
                'rts_shipping_fee_adjustment' => $colNum('RTS ShippingFee Adjustment'),
                'rts_total_shipping_fee_cwt_adjustment' => $colNum('RTS Total ShippingFee CWT Adjustment'),
                'other_adjustment' => $colNum('Other Adjustment'),
                'discount_amount' => $colNum('Discount amount'),
                'total_adjustment' => $colNum('Total Adjustment'),
                // Payment & deductions
                'net_amount' => $colNum('Payment amount'),
                'previous_period_bill_deduction' => $colNum('Previous Period Bill Deduction'),
                'amount_after_deduction' => $colNum('Amount after deduction'),
                'current_period_bill_deduction' => $colNum('Current Period Bill Deduction'),
                'already_deducted_freight_bill' => $col('Already Deducted Freight Bill') ?: null,
                'shipping_fee_difference' => $colNum('Shipping Fee Difference'),
                // Courier timestamps & status
                'courier_creation_time' => $colDate('Creation time'),
                'confirm_status' => $col('Confirm the status') ?: null,
                'confirm_time' => $colDate('Confirm Time'),
                'billing_status' => $col('Billing status') ?: null,
                'email_sending_status' => $col('Email sending status') ?: null,
                'email_sending_time' => $colDate('Email sending time'),
                'status' => 'pending',
            ]);

            $imported++;
        }

        $message = "{$imported} remittance(s) imported.";
        if ($skipped > 0) {
            $message .= " {$skipped} duplicate(s) skipped.";
        }

        return redirect()->route('workspaces.finance.remittances.index', $workspace->slug)
            ->with('success', $message);
    }

    public function clearItems(Request $request, Workspace $workspace, Remittance $remittance)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceRemittances->value, $workspace);
        $this->ensureOwns($workspace, $remittance);

        $deleted = $remittance->items()->delete();

        return redirect()->back()->with('success', "{$deleted} item(s) deleted.");
    }

    public function importItems(Request $request, Workspace $workspace, Remittance $remittance)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceRemittances->value, $workspace);
        $this->ensureOwns($workspace, $remittance);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray();

        if (count($rows) < 2) {
            return redirect()->back()->withErrors(['file' => 'The file contains no data rows.']);
        }

        $header = array_map(fn ($h) => trim((string) $h), $rows[0]);
        $colMap = array_flip($header);

        $required = ['Waybill Number'];
        $missing = array_filter($required, fn ($col) => ! isset($colMap[$col]));
        if ($missing) {
            return redirect()->back()->withErrors(['file' => 'Missing columns: '.implode(', ', $missing)]);
        }

        $parseNumber = fn ($v) => (float) str_replace(',', '', trim((string) $v));
        $col = fn (string $name, array $row) => isset($colMap[$name]) ? trim((string) ($row[$colMap[$name]] ?? '')) : '';

        $now = now();
        $records = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $waybill = $col('Waybill Number', $row);
            if (! $waybill) {
                continue;
            }

            $records[] = [
                'remittance_id' => $remittance->id,
                'waybill_number' => $waybill,
                'order_number' => $col('Order Number', $row) ?: null,
                'shipping_date' => ($sd = $col('Shipping date', $row)) && strtotime($sd) ? $sd : null,
                'sender_city' => $col('Sender City', $row) ?: null,
                'destination_city' => $col('Destination city', $row) ?: null,
                'package_billing_weight' => $parseNumber($col("Package billing Weight\n", $row) ?: $col('Package billing Weight', $row)),
                'item_value' => $parseNumber($col('Item Value', $row)),
                'value_added_fee' => $parseNumber($col('Value-added fee', $row)),
                'receivable_freight' => $parseNumber($col('Receivable Freight', $row)),
                'total_shipping_cost' => $parseNumber($col('Total Shipping Cost', $row)),
                'cod' => $parseNumber($col('Cod', $row)),
                'cod_commission_rate' => $parseNumber($col('COD commission rate', $row)),
                'cod_commission' => $parseNumber($col('COD commission', $row)),
                'cod_commission_vat_fee' => $parseNumber($col('COD commission VAT fee', $row)),
                'shipping_customer_code' => $col('Shipping customer code', $row) ?: null,
                'signing_time' => ($st = $col('SigningTime', $row)) && strtotime($st) ? $st : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Clear existing items then bulk insert
        $remittance->items()->delete();
        RemittanceItem::insert($records);

        return redirect()->back()->with('success', count($records).' item(s) imported.');
    }
}
