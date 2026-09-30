<?php

namespace Modules\Creatives\Models;

use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Creative extends Model
{
    protected $guarded = [];

    protected $casts = [
        'creative_date' => 'date',
        'creator_id' => 'integer',
        'product_id' => 'integer',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
    ];

    /**
     * Alphabet for the public creative code. Drops look-alike characters
     * (0/O, 1/I/L) so codes survive being read aloud or retyped.
     */
    private const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const CODE_LENGTH = 10;

    protected static function booted(): void
    {
        static::creating(function (Creative $creative) {
            $creative->code ??= static::generateCode();
        });
    }

    /** A random 10-character code not yet used by any creative. */
    public static function generateCode(): string
    {
        $max = strlen(self::CODE_ALPHABET) - 1;

        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, $max)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** Calendar-date label for the planned creative date (timezone-safe). */
    public function getCreativeDateLabelAttribute(): ?string
    {
        return $this->creative_date?->format('M j, Y');
    }

    /**
     * How the actual submission date (created_at) compares to the planned
     * creative_date: 'late' (submitted after), 'early' (submitted before),
     * 'on_time' (same calendar day), or null if either date is missing.
     */
    public function getSubmissionStatusAttribute(): ?string
    {
        if (! $this->creative_date || ! $this->created_at) {
            return null;
        }

        $planned = $this->creative_date->toDateString();
        $submitted = $this->created_at->toDateString();

        return match (true) {
            $submitted > $planned => 'late',
            $submitted < $planned => 'early',
            default => 'on_time',
        };
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignedReviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'creative_reviewers', 'creative_id', 'user_id')
            ->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CreativeReview::class);
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(CreativeReview::class)->latestOfMany();
    }
}
