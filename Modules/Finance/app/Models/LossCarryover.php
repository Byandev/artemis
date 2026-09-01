<?php

namespace Modules\Finance\Models;

use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deficit carried into a month, entered against one seller and one product.
 *
 * The finest grain the statements report at, so it adds up in every direction:
 * a person's total is their products' carryovers, a product's is its sellers',
 * and the month's is all of them. Entering it here rather than at the top is
 * what lets those agree.
 *
 * Lives apart from the statement slices, which are snapshots rebuilt on every
 * save — see the migration.
 */
class LossCarryover extends Model
{
    protected $table = 'finance_income_loss_carryovers';

    protected $fillable = [
        'workspace_id',
        'period_month',
        'user_id',
        'product_id',
        'amount',
    ];

    protected $casts = [
        'period_month' => 'date',
        'amount' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
