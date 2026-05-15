<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait LogsActivityForWorkspace
{
    use LogsActivity;

    public static function bootLogsActivityForWorkspace(): void
    {
        static::saved(function (Model $model): void {
            if (method_exists($model, 'stampLatestActivityWorkspace')) {
                $model->stampLatestActivityWorkspace();
            }
        });

        static::deleted(function (Model $model): void {
            if (method_exists($model, 'stampLatestActivityWorkspace')) {
                $model->stampLatestActivityWorkspace();
            }
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function (Model $model): void {
                if (method_exists($model, 'stampLatestActivityWorkspace')) {
                    $model->stampLatestActivityWorkspace();
                }
            });
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['updated_at'])
            ->useLogName(class_basename($this))
            ->setDescriptionForEvent(fn (string $eventName) => sprintf('%s %s', class_basename($this), $eventName));
    }

    public function stampLatestActivityWorkspace(): void
    {
        $workspaceId = $this->getAttribute('workspace_id');

        if (! $workspaceId) {
            try {
                $workspace = request()->route('workspace');
                $workspaceId = is_object($workspace) ? ($workspace->id ?? null) : null;
            } catch (\Throwable) {
                $workspaceId = null;
            }
        }

        if (! $workspaceId) {
            return;
        }

        $activity = $this->activitiesAsSubject()->latest('id')->first();

        if (! $activity) {
            return;
        }

        $activity->forceFill(['workspace_id' => $workspaceId])->saveQuietly();
    }
}
