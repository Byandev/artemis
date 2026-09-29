<?php

namespace Modules\Finance\Models;

use App\Models\Department;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundRequest extends Model
{
    protected $table = 'finance_fund_requests';

    /**
     * The statuses a fund request can move through.
     */
    public const STATUSES = ['pending', 'approved', 'released', 'cancelled'];

    /** Statuses that represent a decision made by an approver. */
    public const APPROVED_STATUSES = ['approved', 'released'];

    /** How the funds can be released, keyed by stored value => label. */
    public const PAYMENT_METHODS = [
        'online_banking' => 'Online Banking',
        'e_wallet' => 'E-Wallet',
        'cheque' => 'Cheque',
        'cash' => 'Cash',
    ];

    /**
     * The methods that send the funds to an account, and so need the bank (or
     * e-wallet provider), account name and account number.
     */
    public const PAYMENT_METHODS_WITH_ACCOUNT = ['online_banking', 'e_wallet'];

    protected $fillable = [
        'workspace_id',
        'request_date',
        'reference_no',
        'requested_by',
        'transaction_type_id',
        'department_id',
        'amount_requested',
        'liquidation_required',
        'liquidation_deadline',
        'payment_method',
        'bank_name',
        'account_name',
        'account_number',
        'approved_by',
        'status',
        'remarks',
    ];

    protected $casts = [
        'request_date' => 'date',
        'amount_requested' => 'decimal:2',
        'liquidation_required' => 'boolean',
        'liquidation_deadline' => 'date:Y-m-d',
    ];

    protected static function booted(): void
    {
        // The attachment rows would go with the request by cascade, but their
        // files would stay in the bucket: delete them one model at a time so
        // media-library removes each.
        static::deleting(fn (FundRequest $fundRequest) => $fundRequest->attachments()->get()->each->delete());
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The users this request's amount is shared among. Each carries an `amount`
     * pivot — their share of the request, the shares summing to the amount
     * requested.
     */
    public function chargeToUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'finance_fund_request_user_shares', 'fund_request_id', 'user_id')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /**
     * The line items the request is for, in the order they were entered. They
     * sum to the amount requested.
     */
    public function particulars(): HasMany
    {
        return $this->hasMany(FundRequestParticular::class, 'fund_request_id')->orderBy('sort_order');
    }

    /**
     * The products this request covers, each with its share of the amount. Set
     * independently of the ad-spend line items.
     */
    public function productShares(): HasMany
    {
        return $this->hasMany(FundRequestProductShare::class, 'fund_request_id')->orderBy('sort_order');
    }

    /**
     * The transactions settling this request. A loose link: several entries may
     * point here and nothing reconciles their amounts against the request.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'fund_request_id');
    }

    /*
     * Relations are named so they do NOT collide with the same-named foreign-key
     * columns on serialization (e.g. a requestedBy() relation would serialize to
     * "requested_by" and overwrite the integer FK the forms rely on).
     */

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The files on this request, one per attachment requirement of its
     * transaction type that it answers.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(FundRequestAttachment::class, 'fund_request_id');
    }

    /**
     * The checklist requirements ticked off on this request, out of its
     * transaction type's checklist.
     */
    public function checkedChecklists(): BelongsToMany
    {
        return $this->belongsToMany(FundRequestChecklistRequirement::class, 'finance_fund_request_checklists', 'fund_request_id', 'checklist_requirement_id')
            ->withTimestamps();
    }
}
