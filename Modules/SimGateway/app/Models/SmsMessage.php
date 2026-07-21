<?php

namespace Modules\SimGateway\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SimGateway\Database\Factories\SmsMessageFactory;
use Modules\SimGateway\Enums\MessageDirection;
use Modules\SimGateway\Enums\MessageStatus;

class SmsMessage extends Model
{
    /** @use HasFactory<SmsMessageFactory> */
    use HasFactory;

    protected $table = 'sim_gateway_sms_messages';

    protected $fillable = [
        'workspace_id',
        'sim_id',
        'direction',
        'from_number',
        'to_number',
        'message',
        'segments',
        'status',
        'provider_message_id',
        'error_message',
        'sent_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'segments' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sim(): BelongsTo
    {
        return $this->belongsTo(Sim::class);
    }

    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', MessageDirection::Outbound);
    }

    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', MessageDirection::Inbound);
    }

    public function scopeLast30Days(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subDays(30));
    }

    protected static function newFactory(): SmsMessageFactory
    {
        return SmsMessageFactory::new();
    }
}
