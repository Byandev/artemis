<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Models\Permission as PermissionModel;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GrantUserPagePermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:grant-page-access {user_email} {workspace_slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant a user all page-related permissions in a specific workspace';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userEmail = $this->argument('user_email');
        $workspaceSlug = $this->argument('workspace_slug');

        // Find user
        $user = User::where('email', $userEmail)->first();
        if (! $user) {
            $this->error("User with email {$userEmail} not found.");

            return 1;
        }

        // Find workspace
        $workspace = Workspace::where('slug', $workspaceSlug)->first();
        if (! $workspace) {
            $this->error("Workspace with slug {$workspaceSlug} not found.");

            return 1;
        }

        // Find user's role in workspace
        $roleId = DB::table('workspace_user')
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->value('role_id');

        if (! $roleId) {
            $this->error("{$userEmail} is not a member of {$workspaceSlug}.");

            return 1;
        }

        // Get all page-related permissions
        $pagePermissions = [
            Permission::ViewPages->value,
            Permission::CreatePages->value,
            Permission::EditPages->value,
            Permission::ArchivePages->value,
            Permission::RefreshPages->value,
        ];

        $permissions = PermissionModel::whereIn('name', $pagePermissions)->get();

        if ($permissions->isEmpty()) {
            $this->error('No page permissions found in the system.');

            return 1;
        }

        // Attach permissions to role
        $attached = 0;
        foreach ($permissions as $permission) {
            $exists = DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permission->id)
                ->exists();

            if (! $exists) {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permission->id,
                ]);
                $attached++;
                $this->line("✓ Granted: {$permission->name}");
            } else {
                $this->line("○ Already had: {$permission->name}");
            }
        }

        $this->info("\nGranted {$attached} new page permissions to {$user->name} ({$userEmail}) in {$workspace->name}");

        return 0;
    }
}
