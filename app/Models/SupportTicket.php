<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'question',
        'bug',
        'feature_request',
        'billing',
        'other',
    ];

    public const STATUSES = [
        'open',
        'in_progress',
        'resolved',
        'closed',
    ];

    protected $fillable = [
        'workspace_id',
        'user_id',
        'reference',
        'category',
        'subject',
        'description',
        'current_url',
        'user_agent',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            if (! $ticket->reference) {
                $ticket->reference = self::generateReference();
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    private static function generateReference(): string
    {
        do {
            $reference = 'SUP-'.strtoupper(bin2hex(random_bytes(3)));
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
