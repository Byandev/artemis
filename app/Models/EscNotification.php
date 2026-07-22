<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscNotification extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'reminder_enabled',
        'reminder_time',
        'reminder_timezone',
        'reminder_style',
        'last_reminded_at',
    ];

    /**
     * Mirrors the column defaults in the migration.
     *
     * Without this, a row made by `firstOrCreate(['user_id' => ...])` would come
     * back with these attributes null in memory — the database applies its
     * defaults on insert, but Eloquent doesn't read them back.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'reminder_enabled' => true,
        'reminder_time' => '20:00:00',
        'reminder_timezone' => 'Asia/Manila',
        'reminder_style' => 'gentle',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reminder_enabled' => 'boolean',
            'last_reminded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
