<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\FundRequestRequest;
use Modules\Finance\Models\FundRequest;
use Modules\Finance\Models\FundRequestAttachment;
use Modules\Finance\Models\TransactionType;
use Modules\Products\Models\Product;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class FundRequestController extends Controller
{
    use AuthorizesRequests;

    protected function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    protected function ensureOwns(Workspace $workspace, FundRequest $requestFund): void
    {
        if ($requestFund->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceRequestFunds->value, $workspace);

        $requestFunds = QueryBuilder::for(
            FundRequest::where('workspace_id', $workspace->id)
                ->with([
                    'requester:id,name',
                    'chargeToUsers:users.id,users.name',
                    'approver:id,name',
                    'productShares',
                    'transactionType:id,name',
                    'department:id,name',
                ])
        )
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where(function ($sub) use ($v) {
                    $sub->where('reference_no', 'like', "%{$v}%");
                })),
                AllowedFilter::exact('status'),
                // charge_to is a pivot now, so the filter matches any request
                // the user bears a share of.
                AllowedFilter::callback('charge_to', fn ($q, $v) => $q->whereHas(
                    'chargeToUsers', fn ($sub) => $sub->where('users.id', $v)
                )),
                AllowedFilter::exact('requested_by'),
            ])
            ->allowedSorts([
                'reference_no',
                'request_date',
                'amount_requested',
                'status',
                'created_at',
                AllowedSort::field('id', 'id'),
            ])
            ->defaultSort('-request_date')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('workspaces/finance/request-funds/index', [
            'workspace' => $workspace,
            'requestFunds' => $requestFunds,
            'statuses' => FundRequest::STATUSES,
            'canApproveStatus' => $request->user()->can(Permission::ApproveFinanceRequestFunds->value, $workspace),
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function create(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceRequestFunds->value, $workspace);

        return Inertia::render('workspaces/finance/request-funds/create', [
            'workspace' => $workspace,
            ...$this->formOptions($request, $workspace),
        ]);
    }

    public function edit(Request $request, Workspace $workspace, FundRequest $requestFund)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceRequestFunds->value, $workspace);
        $this->ensureOwns($workspace, $requestFund);

        $requestFund->load([
            'particulars',
            'chargeToUsers:users.id,users.name',
            'productShares',
            'approver:id,name',
            'checkedChecklists:finance_fund_request_checklist_requirements.id',
        ]);

        return Inertia::render('workspaces/finance/request-funds/edit', [
            'workspace' => $workspace,
            'requestFund' => [
                ...$requestFund->toArray(),
                'checklist_ids' => $requestFund->checkedChecklists->pluck('id'),
                'files' => $this->filesFor($workspace, $requestFund),
            ],
            ...$this->formOptions($request, $workspace, $requestFund),
        ]);
    }

    /**
     * Stream / redirect to one of a request's uploaded attachments. The bucket is
     * private, so this hands out a short-lived signed URL, or streams the bytes
     * when the disk cannot sign one (a local disk in development).
     */
    public function downloadAttachment(Request $request, Workspace $workspace, FundRequest $requestFund, FundRequestAttachment $attachment)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceRequestFunds->value, $workspace);
        $this->ensureOwns($workspace, $requestFund);

        abort_unless($attachment->fund_request_id === $requestFund->id, 404);

        $media = $attachment->file() ?? abort(404);

        $disk = Storage::disk($media->disk);

        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl(
                $media->getPathRelativeToRoot(),
                Carbon::now()->addMinutes(5),
            ));
        }

        return $disk->download($media->getPathRelativeToRoot(), $media->file_name);
    }

    public function store(FundRequestRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceRequestFunds->value, $workspace);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request, $workspace) {
            // New requests always start for approval; the reference number is generated
            // here (never supplied by the client) and approval happens via
            // updateStatus. The request date is the creation date and the
            // requester is the signed-in user — neither is entered on the form.
            $fundRequest = FundRequest::create([
                ...$this->attributesFor($validated, $request),
                'workspace_id' => $workspace->id,
                'reference_no' => $this->nextReferenceNo($workspace),
                'request_date' => now()->toDateString(),
                'requested_by' => $request->user()->id,
                'status' => FundRequest::DEFAULT_STATUS,
                'approved_by' => null,
            ]);

            $this->syncParticulars($fundRequest, $request);
            $this->syncShares($fundRequest, $request, $workspace);
            $this->syncRequirements($fundRequest, $request);
        });

        return redirect()->route('workspaces.finance.request-funds.index', $workspace->slug)
            ->with('success', 'Fund request created.');
    }

    public function update(FundRequestRequest $request, Workspace $workspace, FundRequest $requestFund)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceRequestFunds->value, $workspace);
        $this->ensureOwns($workspace, $requestFund);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request, $workspace, $requestFund) {
            // reference_no is intentionally omitted from validated data, so it stays put.
            $requestFund->update($this->attributesFor($validated, $request));

            $this->syncParticulars($requestFund, $request);
            $this->syncShares($requestFund, $request, $workspace);
            $this->syncRequirements($requestFund, $request);
        });

        return redirect()->route('workspaces.finance.request-funds.index', $workspace->slug)
            ->with('success', 'Fund request updated.');
    }

    public function destroy(Request $request, Workspace $workspace, FundRequest $requestFund)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::DeleteFinanceRequestFunds->value, $workspace);
        $this->ensureOwns($workspace, $requestFund);

        $requestFund->delete();

        return redirect()->route('workspaces.finance.request-funds.index', $workspace->slug)
            ->with('success', 'Fund request deleted.');
    }

    /**
     * Approve / send for liquidation / hold a request. Gated by a dedicated
     * permission so that status changes are separated from ordinary edits. The
     * approver is stamped when moving into an approved / for-liquidation state
     * and cleared otherwise.
     */
    public function updateStatus(Request $request, Workspace $workspace, FundRequest $requestFund)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ApproveFinanceRequestFunds->value, $workspace);
        $this->ensureOwns($workspace, $requestFund);

        $validated = $request->validate([
            'status' => ['required', Rule::in(FundRequest::STATUSES)],
        ]);

        $requestFund->update([
            'status' => $validated['status'],
            'approved_by' => in_array($validated['status'], FundRequest::APPROVED_STATUSES, true)
                ? ($requestFund->approved_by ?? $request->user()->id)
                : null,
        ]);

        return back()->with('success', 'Status updated.');
    }

    /**
     * The next sequential reference number for a workspace, e.g. RF-00007.
     *
     * Carried on from the highest number the workspace has issued rather than
     * from its row count: deleting an older request drops the count while its
     * successors keep their numbers, so counting would hand out one that is
     * still in use and trip the unique (workspace_id, reference_no) index.
     *
     * Called inside the creating transaction, and the read is locked so two
     * requests saved at the same moment can't settle on the same number.
     */
    protected function nextReferenceNo(Workspace $workspace): string
    {
        $prefix = 'RF-';

        // Longest first, so RF-100000 still outranks RF-99999 once five digits
        // are outgrown and the padding stops making lengths comparable.
        $last = FundRequest::where('workspace_id', $workspace->id)
            ->where('reference_no', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(reference_no) DESC')
            ->orderBy('reference_no', 'desc')
            ->lockForUpdate()
            ->value('reference_no');

        $next = (int) substr((string) $last, strlen($prefix)) + 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * The column values for a request. The particulars, charge_to / products
     * pivots, checklist and attachments live in their own tables, so they are
     * stripped here and synced after the row exists. The amount requested is
     * the particulars' total, a deadline is dropped when liquidation isn't
     * required, and the account details when the payment method doesn't send
     * the funds to an account.
     */
    protected function attributesFor(array $validated, FundRequestRequest $request): array
    {
        return [
            ...collect($validated)->except(['particulars', 'charge_to', 'products', 'checklist_ids', 'attachments', 'remove_attachments'])->all(),
            'amount_requested' => $request->requestTotal(),
            'liquidation_required' => $request->boolean('liquidation_required'),
            'liquidation_deadline' => $request->boolean('liquidation_required')
                ? $validated['liquidation_deadline']
                : null,
            // Only online banking and e-wallets go to an account.
            ...collect(['bank_name', 'account_name', 'account_number'])
                ->mapWithKeys(fn ($field) => [$field => $request->needsAccount() ? trim($validated[$field]) : null])
                ->all(),
        ];
    }

    /**
     * Replace a request's particulars with the submitted rows, rewritten rather
     * than diffed: the rows have no natural key, and their order on the form is
     * the order that matters.
     */
    protected function syncParticulars(FundRequest $fundRequest, FundRequestRequest $request): void
    {
        $fundRequest->particulars()->delete();

        $fundRequest->particulars()->createMany(
            collect($request->particulars())
                ->map(fn ($particular, $index) => [...$particular, 'sort_order' => $index])
                ->all()
        );
    }

    /**
     * Replace a request's charge-to and product shares with the submitted sets.
     * Both are rewritten rather than diffed — the product rows have no natural
     * key to sync against, and the order on the form is the order that matters.
     */
    protected function syncShares(FundRequest $fundRequest, FundRequestRequest $request, Workspace $workspace): void
    {
        $fundRequest->chargeToUsers()->sync(
            collect($request->chargeToShares())
                ->mapWithKeys(fn ($share) => [$share['user_id'] => ['amount' => $share['amount']]])
                ->all()
        );

        $shares = $request->productShares();

        $fundRequest->productShares()->delete();

        if ($shares === []) {
            return;
        }

        // Name snapshots, so a row still reads after its product is deleted and
        // so a transaction filled in from this request has something to tag.
        $names = Product::where('workspace_id', $workspace->id)
            ->whereIn('id', array_column($shares, 'product_id'))
            ->pluck('name', 'id');

        $fundRequest->productShares()->createMany(
            collect($shares)->map(fn ($share, $index) => [
                'product_id' => $share['product_id'],
                'product_label' => $names[$share['product_id']] ?? '',
                'amount' => $share['amount'],
                'sort_order' => $index,
            ])->all()
        );
    }

    /**
     * Tick off the submitted checklist items and store the uploaded attachment
     * files, then drop anything that no longer belongs to the request's type —
     * switching the type leaves the old type's files and ticks behind otherwise.
     * Validation has already confined both to the chosen type.
     */
    protected function syncRequirements(FundRequest $fundRequest, FundRequestRequest $request): void
    {
        $fundRequest->checkedChecklists()->sync($request->input('checklist_ids', []));

        $type = $fundRequest->transactionType()->with('attachments:id')->first();
        $allowed = $type?->attachments->pluck('id')->all() ?? [];
        $replaced = array_map('intval', array_keys($request->file('attachments', [])));
        $removed = array_map('intval', $request->input('remove_attachments', []));

        // Deleted one model at a time so media-library removes each file from
        // the bucket (a query-builder delete would orphan them).
        $fundRequest->attachments()->get()
            ->filter(fn (FundRequestAttachment $attachment) => ! in_array($attachment->attachment_requirement_id, $allowed, true)
                || in_array($attachment->attachment_requirement_id, $replaced, true)
                || in_array($attachment->attachment_requirement_id, $removed, true))
            ->each->delete();

        foreach ($request->file('attachments', []) as $requirementId => $file) {
            $fundRequest->attachments()
                ->create(['attachment_requirement_id' => (int) $requirementId])
                ->addMedia($file)
                ->toMediaCollection(FundRequestAttachment::FILE_COLLECTION);
        }
    }

    /**
     * The request's uploaded files, as the form shows them: which attachment
     * requirement each answers, its name and size, and where to download it.
     */
    protected function filesFor(Workspace $workspace, FundRequest $fundRequest): array
    {
        return $fundRequest->attachments()->with('media')->get()
            ->filter(fn (FundRequestAttachment $attachment) => $attachment->file())
            ->map(fn (FundRequestAttachment $attachment) => [
                'id' => $attachment->id,
                'attachment_requirement_id' => $attachment->attachment_requirement_id,
                'file_name' => $attachment->file()->file_name,
                'size' => $attachment->file()->size,
                'url' => route('workspaces.finance.request-funds.attachments.show', [$workspace->slug, $fundRequest->id, $attachment->id]),
            ])
            ->values()
            ->all();
    }

    /**
     * Option lists for the create / edit form. Each transaction type carries the
     * attachments and checklist it calls for, and whether it picks a product
     * per particular, so the form can show them the moment the type is picked.
     * Only fund-requestable types are offered, plus the type of the request
     * being edited, so an edit doesn't lose its type when that type has since
     * been switched off.
     */
    protected function formOptions(Request $request, Workspace $workspace, ?FundRequest $fundRequest = null): array
    {
        return [
            'users' => $workspace->users()->orderBy('users.name')->get(['users.id', 'users.name']),
            'products' => $this->productOptions($request, $workspace),
            'transactionTypes' => TransactionType::where('workspace_id', $workspace->id)
                ->where(fn ($q) => $q->fundRequestable()
                    ->when($fundRequest?->transaction_type_id, fn ($q, $id) => $q->orWhere('id', $id)))
                ->with(['attachments:id,name', 'checklists:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'nature', 'fund_requestable_per_product']),
            'paymentMethods' => collect(FundRequest::PAYMENT_METHODS)
                ->map(fn ($label, $value) => [
                    'value' => $value,
                    'label' => $label,
                    'needs_account' => in_array($value, FundRequest::PAYMENT_METHODS_WITH_ACCOUNT, true),
                    'providers' => FundRequest::PAYMENT_PROVIDERS[$value] ?? [],
                ])
                ->values(),
            'departments' => Department::ofWorkspace($workspace)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Products the user may request against, for the product-share picker.
     */
    protected function productOptions(Request $request, Workspace $workspace)
    {
        return Product::ofWorkspace($workspace)
            ->visibleTo($request->user(), $workspace)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
