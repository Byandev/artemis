<?php

namespace Modules\Pancake\Models;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The auto-fill webhook's record of one order — see AutoFillOrderAddress.
 */
class AddressAutofill extends Model
{
    public const QUEUED = 'queued';

    /** The address was written back to Pancake. */
    public const UPDATED = 'updated';

    /** Would have been written back, but dry-run is on. */
    public const DRY_RUN = 'dry_run';

    /** An address was found but not every level matched, or the AI was unsure. */
    public const NEEDS_REVIEW = 'needs_review';

    /** The customer has not given an address in the chat (yet). */
    public const NO_ADDRESS = 'no_address';

    /** Nothing to do: no conversation, no page token, address already complete… */
    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    /**
     * Outcomes worth another go when Pancake sends the order again — the
     * customer may have typed the address since, or the error may have cleared.
     */
    public const RETRYABLE = [self::NO_ADDRESS, self::FAILED];

    protected $table = 'pancake_address_autofills';

    protected $guarded = [];

    protected $casts = [
        'result' => 'array',
        'payload' => 'array',
        'processed_at' => 'datetime',
        'ai_cost_usd' => 'float',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
