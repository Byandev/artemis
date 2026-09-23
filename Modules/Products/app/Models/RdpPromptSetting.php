<?php

namespace Modules\Products\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a workspace wants its product names generated — the brand's half of the
 * prompt, and how many candidates to ask for.
 *
 * Only the voice and the constraints are editable. The rules that keep the
 * answer usable — one sentence of reasoning per name, varied angles, never
 * naming a delivery form the brief did not ask for — are appended by
 * RdpNameSuggester and cannot be edited away, because the "Pick a name" grid
 * depends on them.
 */
class RdpPromptSetting extends Model
{
    /** What the dialog's "Reset to default" puts back. */
    public const DEFAULT_PROMPT = 'You name over-the-counter health products for a Philippine direct-response brand. Names are 2–4 words, plain English, no trademarks, no medical claims of cure.';

    public const DEFAULT_COUNT = 10;

    /** The dialog's stepper stops here, and so does validation. */
    public const MAX_COUNT = 20;

    /**
     * The packshot default, worded as the design has it.
     *
     * Whether the render carries branding lives in this text — "a printed
     * label carrying the name" — rather than in a separate switch, so the
     * prompt is the single control. Edit it to ask for blank packaging if the
     * lettering ever comes back misspelled.
     */
    public const DEFAULT_PACKSHOT_PROMPT = 'Real studio product photograph on a plain neutral light grey seamless backdrop. Professional softbox lighting, crisp focus on the product, a printed label carrying the name, and a soft natural ground shadow.';

    public const DEFAULT_PACKSHOT_COUNT = 5;

    /** Each option is a paid image, so this ceiling is lower than the names'. */
    public const MAX_PACKSHOT_COUNT = 8;

    protected $fillable = [
        'workspace_id',
        'naming_prompt',
        'name_count',
        'packshot_prompt',
        'packshot_count',
    ];

    protected $casts = [
        'name_count' => 'integer',
        'packshot_count' => 'integer',
    ];

    protected $attributes = [
        'name_count' => self::DEFAULT_COUNT,
        'packshot_count' => self::DEFAULT_PACKSHOT_COUNT,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The workspace's settings, or an unsaved instance carrying the defaults —
     * so a workspace that has never opened the dialog still generates.
     */
    public static function forWorkspace(Workspace $workspace): self
    {
        return static::firstOrNew(['workspace_id' => $workspace->id]);
    }

    /** The editable half of the prompt, falling back to the default. */
    public function prompt(): string
    {
        return filled($this->naming_prompt) ? $this->naming_prompt : self::DEFAULT_PROMPT;
    }

    public function count(): int
    {
        return $this->name_count ?: self::DEFAULT_COUNT;
    }

    /** The editable half of the packshot prompt, falling back to the default. */
    public function packshotPrompt(): string
    {
        return filled($this->packshot_prompt) ? $this->packshot_prompt : self::DEFAULT_PACKSHOT_PROMPT;
    }

    public function packshotCount(): int
    {
        return $this->packshot_count ?: self::DEFAULT_PACKSHOT_COUNT;
    }
}
