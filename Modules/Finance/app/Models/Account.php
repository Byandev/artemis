<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $table = 'finance_accounts';

    protected $fillable = [
        'workspace_id',
        'name',
        'opening_balance',
        'currency',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getCurrentBalanceAttribute(): string
    {
        $in = (float) $this->transactions()->where('type', 'in')->sum('amount');
        $out = (float) $this->transactions()->where('type', 'out')->sum('amount');

        return number_format(((float) $this->opening_balance) + $in - $out, 2, '.', '');
    }

    public function getTotalInAttribute(): string
    {
        return number_format((float) $this->transactions()->where('type', 'in')->sum('amount'), 2, '.', '');
    }

    public function getTotalOutAttribute(): string
    {
        return number_format((float) $this->transactions()->where('type', 'out')->sum('amount'), 2, '.', '');
    }
}
