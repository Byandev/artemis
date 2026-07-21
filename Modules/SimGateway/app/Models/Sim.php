<?php

namespace Modules\SimGateway\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\SimGateway\Database\Factories\SimFactory;
use Modules\SimGateway\Enums\SimCarrier;
use Modules\SimGateway\Enums\SimStatus;

class Sim extends Model
{
    /** @use HasFactory<SimFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'sim_gateway_sims';

    protected $fillable = [
        'workspace_id',
        'iccid',
        'phone_number',
        'carrier',
        'port_number',
        'label',
        'status',
        'activated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'carrier' => SimCarrier::class,
            'status' => SimStatus::class,
            'activated_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function smsMessages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function scheduledMessages(): HasMany
    {
        return $this->hasMany(ScheduledMessage::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SimStatus::Active);
    }

    protected static function newFactory(): SimFactory
    {
        return SimFactory::new();
    }
}
