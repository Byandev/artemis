<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Models\FundRequestAttachmentRequirement;
use Modules\Finance\Models\FundRequestChecklistRequirement;
use Modules\Finance\Models\TransactionType;

/**
 * Finance management: the workspace's attachment and checklist requirements,
 * and which transaction types call for each. A requirement is the workspace's,
 * so one can be linked to any number of types; renaming or deleting it touches
 * every type it is linked to.
 */
class ManagementController extends Controller
{
    use AuthorizesRequests;

    /**
     * Route `{kind}` => [requirement model, TransactionType relation, label for
     * flash messages].
     *
     * @var array<string, array{class-string<Model>, string, string}>
     */
    protected const KINDS = [
        'attachments' => [FundRequestAttachmentRequirement::class, 'attachments', 'Attachment'],
        'checklists' => [FundRequestChecklistRequirement::class, 'checklists', 'Checklist item'],
    ];

    protected function guard(Request $request, Workspace $workspace, ?TransactionType $transactionType = null): void
    {
        abort_unless($workspace->finance_module_enabled, 404);

        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        abort_if($transactionType && $transactionType->workspace_id !== $workspace->id, 404);
    }

    /** One of the workspace's requirements of the given kind; another workspace's 404s. */
    protected function requirement(Workspace $workspace, string $kind, int $id): Model
    {
        return self::KINDS[$kind][0]::where('workspace_id', $workspace->id)->findOrFail($id);
    }

    protected function rules(Workspace $workspace, string $kind, ?int $ignoreId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique(self::KINDS[$kind][0], 'name')
                    ->where('workspace_id', $workspace->id)
                    ->ignore($ignoreId),
            ],
        ];
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceTransactions->value, $workspace);

        $requirements = fn (string $model) => $model::where('workspace_id', $workspace->id)
            ->withCount('transactionTypes')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('workspaces/finance/management/index', [
            'workspace' => $workspace,
            'types' => TransactionType::where('workspace_id', $workspace->id)
                ->with(['attachments:id,name', 'checklists:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'nature']),
            'requirements' => [
                'attachments' => $requirements(FundRequestAttachmentRequirement::class),
                'checklists' => $requirements(FundRequestChecklistRequirement::class),
            ],
        ]);
    }

    /**
     * Add a requirement to the workspace, linking it straight to the type it was
     * added from, if any.
     */
    public function store(Request $request, Workspace $workspace, string $kind)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceTransactions->value, $workspace);

        $validated = $request->validate([
            ...$this->rules($workspace, $kind),
            'transaction_type_id' => [
                'nullable',
                Rule::exists('finance_transaction_types', 'id')->where('workspace_id', $workspace->id),
            ],
        ]);

        $requirement = self::KINDS[$kind][0]::create([
            'workspace_id' => $workspace->id,
            'name' => trim($validated['name']),
        ]);

        if (! empty($validated['transaction_type_id'])) {
            $requirement->transactionTypes()->attach($validated['transaction_type_id']);
        }

        return redirect()->back()->with('success', self::KINDS[$kind][2].' added.');
    }

    public function update(Request $request, Workspace $workspace, string $kind, int $id)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceTransactions->value, $workspace);

        $requirement = $this->requirement($workspace, $kind, $id);
        $validated = $request->validate($this->rules($workspace, $kind, $requirement->id));

        $requirement->update(['name' => trim($validated['name'])]);

        return redirect()->back()->with('success', self::KINDS[$kind][2].' renamed.');
    }

    public function destroy(Request $request, Workspace $workspace, string $kind, int $id)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::DeleteFinanceTransactions->value, $workspace);

        $this->requirement($workspace, $kind, $id)->delete();

        return redirect()->back()->with('success', self::KINDS[$kind][2].' deleted.');
    }

    /** Have the type call for one of the workspace's requirements. */
    public function link(Request $request, Workspace $workspace, TransactionType $transactionType, string $kind, int $id)
    {
        $this->guard($request, $workspace, $transactionType);
        $this->authorize(Permission::EditFinanceTransactions->value, $workspace);

        $requirement = $this->requirement($workspace, $kind, $id);
        $transactionType->{self::KINDS[$kind][1]}()->syncWithoutDetaching([$requirement->id]);

        return redirect()->back()->with('success', self::KINDS[$kind][2].' added to '.$transactionType->name.'.');
    }

    /** Stop the type calling for a requirement; the requirement itself stays. */
    public function unlink(Request $request, Workspace $workspace, TransactionType $transactionType, string $kind, int $id)
    {
        $this->guard($request, $workspace, $transactionType);
        $this->authorize(Permission::EditFinanceTransactions->value, $workspace);

        $requirement = $this->requirement($workspace, $kind, $id);
        $transactionType->{self::KINDS[$kind][1]}()->detach($requirement->id);

        return redirect()->back()->with('success', self::KINDS[$kind][2].' removed from '.$transactionType->name.'.');
    }
}
