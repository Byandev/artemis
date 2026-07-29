<?php

namespace Modules\EscTracker\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\EscTracker\Database\Factories\DailyEscRecordFactory;

class DailyEscRecord extends Model
{
    /** @use HasFactory<DailyEscRecordFactory> */
    use HasFactory;

    /**
     * Laravel's convention resolves factories to `Database\Factories\...`, which
     * doesn't hold inside a module — point it at the module's factory instead.
     */
    protected static function newFactory(): Factory
    {
        return DailyEscRecordFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'record_date',
        'learning_text',
        'learning_completed',
        'movement_text',
        'movement_image_url',
        'movement_completed',
        'meditation_completed',
        'meditation_url',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'learning_completed' => 'boolean',
            'movement_completed' => 'boolean',
            'meditation_completed' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
