<?php

namespace App\Models;

use App\Enums\IntegrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A third-party account a user has connected.
 *
 * Holds a token and nothing else that could be used to sign in as them: the
 * password is exchanged for the token when they connect and then dropped, and
 * the account address is not kept either. A leak of this table costs whatever
 * the tokens can read, and cannot be replayed against the person's other
 * accounts the way a reused password could.
 */
class UserIntegration extends Model
{
    /**
     * Every column except the owner: nothing here is ever bound from request
     * input — the token comes back from the service and the sync state is set
     * by the fetch job — so guarding them buys nothing and silently dropping
     * one on updateOrCreate costs a great deal.
     *
     * @var list<string>
     */
    protected $fillable = ['service', 'token', 'last_synced_at', 'last_error'];

    /**
     * The token never belongs in a response — not in an Inertia payload, not in
     * a log line, not in a serialized model anywhere.
     *
     * @var list<string>
     */
    protected $hidden = ['token'];

    protected $casts = [
        'service' => IntegrationService::class,
        // Reversible: the integration sends this back to the service verbatim.
        'token' => 'encrypted',
        'last_synced_at' => 'datetime',
    ];

    /** Whether a token is stored, read without decrypting it. */
    public function hasToken(): bool
    {
        return ! empty($this->getAttributes()['token'] ?? null);
    }

    /**
     * Whether the last unattended fetch failed, which in practice means the
     * token was revoked or expired and the person has to reconnect. Cleared by
     * the next successful fetch.
     */
    public function needsReconnect(): bool
    {
        return filled($this->last_error);
    }

    public function scopeForService(Builder $query, IntegrationService $service): Builder
    {
        return $query->where('service', $service);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
