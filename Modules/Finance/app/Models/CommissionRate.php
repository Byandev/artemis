<?php

namespace Modules\Finance\Models;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-user, per-product commission rate (fraction of the product's net profit).
 * Read on the per-user income-statement product breakdown; display-only.
 */
class CommissionRate extends Model
{
    protected $table = 'finance_commission_rates';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'product_id',
        'rate',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
