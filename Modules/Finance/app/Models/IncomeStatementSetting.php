<?php

namespace Modules\Finance\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeStatementSetting extends Model
{
    protected $table = 'finance_income_statement_settings';

    protected $fillable = [
        'workspace_id',
        'cod_fee_rate',
        'vat_rate',
        'advisory_rate',
        'advisory_delivered_rate',
    ];

    protected $casts = [
        'cod_fee_rate' => 'decimal:4',
        'vat_rate' => 'decimal:4',
        'advisory_rate' => 'decimal:4',
        'advisory_delivered_rate' => 'decimal:4',
    ];

    /** Default rates when a workspace has no saved settings yet. */
    public const DEFAULT_COD_FEE_RATE = 0.02;

    public const DEFAULT_VAT_RATE = 0.12;

    /** Advisory share of Gross Profit (gencys-partner workspaces only). */
    public const DEFAULT_ADVISORY_RATE = 0.30;

    /** The alternative basis: a share of Delivered rather than of Gross Profit. */
    public const DEFAULT_ADVISORY_DELIVERED_RATE = 0.09;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
