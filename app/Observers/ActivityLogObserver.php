<?php

namespace App\Observers;

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Facades\Activity;
use App\Models\Workspace;
use App\Providers\ActivityLogServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Generic Eloquent observer that records create / update / delete / restore as
 * `data` activity logs. Attach to curated models via the
 * {@see ActivityLogServiceProvider} — do NOT attach it to
 * ActivityLog itself (it would recurse).
 *
 * Only attribute *names* are stored for changes, never values, so secrets such
 * as passwords/tokens are not leaked into the audit trail.
 */
class ActivityLogObserver
{
    /** Attributes never worth reporting as a change. */
    private const IGNORED = ['updated_at', 'created_at', 'remember_token'];

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $changed = array_keys(Arr::except($model->getChanges(), self::IGNORED));

        // Nothing meaningful changed (e.g. only timestamps) — skip the noise.
        if (empty($changed)) {
            return;
        }

        $this->record($model, 'updated', $changed);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restored');
    }

    /**
     * @param  array<int, string>  $changed
     */
    private function record(Model $model, string $verb, array $changed = []): void
    {
        $base = class_basename($model);
        $action = Str::snake($base).'.'.$verb;
        $label = Str::headline($base);
        $name = $this->displayName($model);

        // Prefer a human-readable name ("Role \"Manager\" created") and fall back
        // to the primary key when the model has no name-like attribute.
        $subject = $name !== null ? "\"{$name}\"" : "#{$model->getKey()}";

        $builder = Activity::build()
            ->category(LogCategory::Data)
            ->action($action)
            ->status(LogStatus::Success)
            ->message("{$label} {$subject} {$verb}")
            ->metadata(array_filter([
                'model' => $model::class,
                'id' => $model->getKey(),
                'name' => $name,
                'changed' => $changed ?: null,
            ]));

        // Attribute the entry to the signed-in user, or mark it system-generated
        // (queued jobs, console commands) when there is no authenticated actor.
        Auth::check() ? $builder->asUser() : $builder->asSystem();

        if ($model instanceof Workspace) {
            $builder->workspace($model->getKey());
        } elseif ($workspaceId = $model->getAttribute('workspace_id')) {
            $builder->workspace((int) $workspaceId);
        }

        $builder->save();

        // Tell the catch-all LogRequestActivity middleware that this request's
        // state change already has a dedicated, richer entry, so it can skip
        // writing a duplicate. Harmless in console/queue context (no middleware
        // reads the flag there).
        if (! App::runningInConsole()) {
            request()->attributes->set('activity_logged', true);
        }
    }

    /**
     * Best-effort human-readable name for the record, used to make log messages
     * read "Role \"Manager\" created" instead of "Role #5 created". Returns null
     * when the model has no recognisable name-like attribute.
     */
    private function displayName(Model $model): ?string
    {
        foreach (['name', 'title', 'label', 'display_name', 'email', 'slug'] as $key) {
            $value = $model->getAttribute($key);

            if (is_scalar($value) && (string) $value !== '') {
                return Str::limit((string) $value, 100, '');
            }
        }

        return null;
    }
}
