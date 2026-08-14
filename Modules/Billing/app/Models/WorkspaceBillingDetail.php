<?php

namespace Modules\Billing\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-workspace billing details — the "bill to" side of an invoice. The seller
 * ("from") side is platform-wide and lives in config/invoice.php.
 */
class WorkspaceBillingDetail extends Model
{
    protected $fillable = [
        'workspace_id',
        'billing_name',
        'billing_address',
        'billing_email',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The workspace's billing details, or an unsaved instance when it has
     * never filled them in — so callers can read the fields either way.
     */
    public static function forWorkspace(int $workspaceId): self
    {
        return static::firstOrNew(['workspace_id' => $workspaceId]);
    }
}
