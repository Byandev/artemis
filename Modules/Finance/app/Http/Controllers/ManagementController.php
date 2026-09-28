<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Models\TransactionType;

/**
 * Finance management: every transaction type alongside the attachments it calls
 * for and the checklist it is checked against. Each type owns both lists — they
 * are created, renamed and deleted here, under the type.
 */
class ManagementController extends Controller
{
    use AuthorizesRequests;

    /** Route `{kind}` => [TransactionType relation, label for flash messages]. */
    protected const KINDS = [
        'attachments' => ['attachments', 'Attachment'],
        'checklists' => ['checklists', 'Checklist item'],
    ];

    protected function guard(Request $request, Workspace $workspace, ?TransactionType $transactionType = null): void
    {
        abort_unless($workspace->finance_module_enabled, 404);

        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        abort_if($transactionType && $transactionType->workspace_id !== $workspace->id, 404);
    }

    /** The type's list of the given kind (attachments or checklist items). */
    protected function items(TransactionType $transactionType, string $kind): HasMany
    {
        return $transactionType->{self::KINDS[$kind][0]}();
    }

    protected function rules(HasMany $items, ?int $ignoreId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique($items->getRelated()->getTable(), 'name')
                    ->where('transaction_type_id', $items->getParentKey())
                    ->ignore($ignoreId),
            ],
        ];
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceTransactions->value, $workspace);

        return Inertia::render('workspaces/finance/management/index', [
            'workspace' => $workspace,
            'types' => TransactionType::where('workspace_id', $workspace->id)
                ->with(['attachments:id,transaction_type_id,name', 'checklists:id,transaction_type_id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'nature']),
        ]);
    }

    public function store(Request $request, Workspace $workspace, TransactionType $transactionType, string $kind)
    {
        $this->guard($request, $workspace, $transactionType);
        $this->authorize(Permission::CreateFinanceTransactions->value, $workspace);

        $items = $this->items($transactionType, $kind);
        $validated = $request->validate($this->rules($items));

        $items->create([
            'workspace_id' => $workspace->id,
            'name' => trim($validated['name']),
        ]);

        return redirect()->back()->with('success', self::KINDS[$kind][1].' added.');
    }

    public function update(Request $request, Workspace $workspace, TransactionType $transactionType, string $kind, int $id)
    {
        $this->guard($request, $workspace, $transactionType);
        $this->authorize(Permission::EditFinanceTransactions->value, $workspace);

        // Looked up through the type, so another type's row 404s.
        $items = $this->items($transactionType, $kind);
        $item = (clone $items)->findOrFail($id);
        $validated = $request->validate($this->rules($items, $item->id));

        $item->update(['name' => trim($validated['name'])]);

        return redirect()->back()->with('success', self::KINDS[$kind][1].' renamed.');
    }

    public function destroy(Request $request, Workspace $workspace, TransactionType $transactionType, string $kind, int $id)
    {
        $this->guard($request, $workspace, $transactionType);
        $this->authorize(Permission::DeleteFinanceTransactions->value, $workspace);

        $this->items($transactionType, $kind)->findOrFail($id)->delete();

        return redirect()->back()->with('success', self::KINDS[$kind][1].' deleted.');
    }
}
