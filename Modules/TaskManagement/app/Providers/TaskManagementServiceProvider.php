<?php

namespace Modules\TaskManagement\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\TaskManagement\Models\Attachment;
use Modules\TaskManagement\Models\Comment;
use Modules\TaskManagement\Models\Folder;
use Modules\TaskManagement\Models\Label;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\Task;
use Modules\TaskManagement\Models\TaskList;
use Modules\TaskManagement\Models\TaskStatus;
use Modules\TaskManagement\Policies\AttachmentPolicy;
use Modules\TaskManagement\Policies\CommentPolicy;
use Modules\TaskManagement\Policies\FolderPolicy;
use Modules\TaskManagement\Policies\LabelPolicy;
use Modules\TaskManagement\Policies\SpacePolicy;
use Modules\TaskManagement\Policies\TaskListPolicy;
use Modules\TaskManagement\Policies\TaskPolicy;
use Modules\TaskManagement\Policies\TaskStatusPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class TaskManagementServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'TaskManagement';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'taskmanagement';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Space-role policies for every task-management model. Listed rather than
     * left to discovery so the Attachment policy is found even though the
     * model extends the media library's Media.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Space::class => SpacePolicy::class,
        Folder::class => FolderPolicy::class,
        TaskList::class => TaskListPolicy::class,
        TaskStatus::class => TaskStatusPolicy::class,
        Label::class => LabelPolicy::class,
        Task::class => TaskPolicy::class,
        Comment::class => CommentPolicy::class,
        Attachment::class => AttachmentPolicy::class,
    ];

    public function boot(): void
    {
        parent::boot();

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
