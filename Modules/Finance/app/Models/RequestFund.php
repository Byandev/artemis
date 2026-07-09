<?php

namespace Modules\Finance\Models;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestFund extends Model
{
    protected $table = 'finance_request_funds';

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
        'charge_to',
        'purpose',
        'amount_requested',
        'date_needed',
        'approved_by',
        'status',
        'remarks',
    ];

    protected $casts = [
        'request_date' => 'date',
        'date_needed' => 'date',
        'amount_requested' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
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

    public function chargeToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'charge_to');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
