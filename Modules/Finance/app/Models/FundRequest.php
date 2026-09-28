<?php

namespace Modules\Finance\Models;

use App\Models\Department;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class FundRequest extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * The files uploaded against the attachments the request's transaction type
     * calls for. Each carries the `attachment_id` it answers as a custom
     * property; one file per attachment.
     */
    public const ATTACHMENTS_COLLECTION = 'fund_request_attachments';

    protected $table = 'finance_fund_requests';

    /**
     * The statuses a fund request can move through.
     */
    public const STATUSES = ['pending', 'approved', 'released', 'cancelled'];

    /** Statuses that represent a decision made by an approver. */
    public const APPROVED_STATUSES = ['approved', 'released'];

    protected $fillable = [
        'workspace_id',
        'request_date',
        'reference_no',
        'requested_by',
        'transaction_type_id',
        'department_id',
        'amount_requested',
        'approved_by',
        'status',
        'remarks',
    ];

    protected $casts = [
        'request_date' => 'date',
        'amount_requested' => 'decimal:2',
    ];

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

    public function registerMediaCollections(): void
    {
        // No acceptsMimeTypes() on purpose — see WorkspaceChecklistCompletion:
        // request validation owns what is accepted and returns a field error.
        $this->addMediaCollection(static::ATTACHMENTS_COLLECTION)
            ->useDisk(config('filesystems.fund_request_attachment_disk'));
    }

    /**
     * The checklist items ticked off on this request, out of its transaction
     * type's checklist.
     */
    public function checkedChecklists(): BelongsToMany
    {
        return $this->belongsToMany(FundRequestChecklist::class, 'finance_fund_request_checklist_checks', 'fund_request_id', 'checklist_id')
            ->withTimestamps();
    }
}
