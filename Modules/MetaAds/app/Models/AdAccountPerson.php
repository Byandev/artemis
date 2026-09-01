<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person Meta reports as having access to an ad account, as returned by
 * `GET /act_<id>/assigned_users`. Mirrors the People list in Business Manager.
 */
class AdAccountPerson extends Model
{
    protected $table = 'meta_ads_account_people';

    protected $guarded = [];

    protected $casts = [
        // Meta user IDs are bigints that exceed JS Number.MAX_SAFE_INTEGER.
        // Cast to string so JSON keeps them exact for the frontend.
        'meta_user_id' => 'string',
        'meta_ads_account_id' => 'string',
        'tasks' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Meta's ad-account tasks in descending order of privilege. The first task
     * a person holds decides the label we show, matching how Business Manager
     * summarises a permission set as a single role.
     */
    /** Complete list, read from a business portfolio's assigned_users. */
    public const SOURCE_PORTFOLIO = 'portfolio';

    /**
     * Partial list, reverse-looked-up from the Facebook users who connected
     * Artemis. The only thing Meta offers for accounts in no portfolio.
     */
    public const SOURCE_CONNECTED_USER = 'connected_user';

    private const ROLE_BY_TASK = [
        'MANAGE' => 'Admin',
        'ADVERTISE' => 'Advertiser',
        'DRAFT' => 'Draft',
        'ANALYZE' => 'Analyst',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'meta_ads_account_id');
    }

    /**
     * Collapse a Meta task list into the single role label Business Manager shows.
     *
     * @param  array<int, string>|null  $tasks
     */
    public static function roleFromTasks(?array $tasks): ?string
    {
        if (empty($tasks)) {
            return null;
        }

        $held = array_map('strtoupper', $tasks);

        foreach (self::ROLE_BY_TASK as $task => $label) {
            if (in_array($task, $held, true)) {
                return $label;
            }
        }

        // Meta occasionally ships new task names (e.g. AA_ANALYZE); show the raw
        // value rather than dropping the person off the list entirely.
        return ucfirst(strtolower(str_replace('_', ' ', $held[0])));
    }
}
