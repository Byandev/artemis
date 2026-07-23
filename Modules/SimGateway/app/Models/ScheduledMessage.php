<?php

namespace Modules\SimGateway\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SimGateway\Database\Factories\ScheduledMessageFactory;
use Modules\SimGateway\Enums\ScheduledMessageStatus;

class ScheduledMessage extends Model
{
    /** @use HasFactory<ScheduledMessageFactory> */
    use HasFactory;

    protected $table = 'sim_gateway_scheduled_messages';

    protected $fillable = [
        'workspace_id',
        'sim_id',
        'to_number',
        'message',
        'scheduled_at',
        'status',
        'sent_at',
        'sms_message_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ScheduledMessageStatus::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
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

    public function smsMessage(): BelongsTo
    {
        return $this->belongsTo(SmsMessage::class);
    }

    protected static function newFactory(): ScheduledMessageFactory
    {
        return ScheduledMessageFactory::new();
    }
}
