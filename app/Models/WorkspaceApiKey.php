<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WorkspaceApiKey extends Model
{
    protected $fillable = ['workspace_id', 'name', 'key', 'key_encrypted', 'key_prefix', 'last_used_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Generate a new raw API key and return both the raw value and the model attributes.
     * The raw key is shown to the user exactly once — never stored.
     */
    public static function generate(): array
    {
        $raw    = 'art_' . Str::random(40);
        $hashed = hash('sha256', $raw);
        $prefix = substr($raw, 0, 8);

        return [
            'raw'           => $raw,
            'key'           => $hashed,
            'key_encrypted' => Crypt::encryptString($raw),
            'prefix'        => $prefix,
        ];
    }

    public function reveal(): string
    {
        return Crypt::decryptString($this->key_encrypted);
    }

    /**
     * Find an API key record by a raw key value.
     */
    public static function findByRawKey(string $raw): ?self
    {
        return static::where('key', hash('sha256', $raw))->first();
    }
}
