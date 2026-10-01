<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class WorkspaceChecklistCompletion extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * Screenshots, receipts, or documents evidencing that the checklist item
     * was actually done. Required on Page checklists.
     */
    public const PROOF_COLLECTION = 'proof_of_completion';

    protected $fillable = [
        'workspace_id',
        'workspace_checklist_id',
        'target_type',
        'target_id',
        'checked_by',
        'checked_at',
        'note',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(static::PROOF_COLLECTION)
            // One file per completion: re-uploading replaces the old proof
            // rather than leaving a reviewer to guess which one is current.
            ->singleFile()
            // No acceptsMimeTypes() here on purpose — see Invoice. What is
            // accepted is enforced by request validation, which returns a field
            // error; media-library re-sniffs the stored bytes and throws,
            // turning an iPhone HEIC reported as image/heif into a 500.
            ->useDisk(config('filesystems.checklist_proof_disk'));
    }

    /**
     * The proof on file for this completion, if one has been attached.
     */
    public function proof(): ?Media
    {
        return $this->getFirstMedia(static::PROOF_COLLECTION);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(WorkspaceChecklist::class, 'workspace_checklist_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
