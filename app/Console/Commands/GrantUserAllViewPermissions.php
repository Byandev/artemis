<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Models\Permission as PermissionModel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GrantUserAllViewPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:grant-all-view {user_email} {workspace_slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant a user all view-related permissions in a specific workspace';

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

        // Get all view-related and manage permissions
        $viewPermissions = [
            // Pages
            Permission::ViewPages->value,
            Permission::CreatePages->value,
            Permission::EditPages->value,
            Permission::ArchivePages->value,
            Permission::RefreshPages->value,
            // Shops
            Permission::ViewShops->value,
            Permission::RefreshShops->value,
            // Products
            Permission::ViewProducts->value,
            Permission::CreateProducts->value,
            Permission::EditProducts->value,
            Permission::DeleteProducts->value,
            Permission::ViewProductForms->value,
            Permission::ManageProductForms->value,
            Permission::ViewTargetMarkets->value,
            Permission::ManageTargetMarkets->value,
            Permission::ViewProductResearch->value,
            Permission::ManageProductResearch->value,
            // RTS
            Permission::ViewRtsAnalytics->value,
            Permission::ViewParcelJourneyTemplates->value,
            Permission::ManageParcelJourneyTemplates->value,
            // CSR
            Permission::ViewCsrManagement->value,
            Permission::EditCsrEmployees->value,
            Permission::ViewCsrAnalytics->value,
            // Members
            Permission::ViewMembers->value,
            Permission::InviteMembers->value,
            Permission::EditMembers->value,
            Permission::RemoveMembers->value,
            Permission::ResetMemberPassword->value,
            // Roles
            Permission::ViewRoles->value,
            Permission::CreateRoles->value,
            Permission::EditRoles->value,
            Permission::DeleteRoles->value,
            Permission::ManageRolePermissions->value,
            // Teams
            Permission::ViewTeams->value,
            Permission::CreateTeams->value,
            Permission::EditTeams->value,
            Permission::DeleteTeams->value,
            Permission::ManageSchedule->value,
            // Inventory
            Permission::ViewInventoryItems->value,
            Permission::CreateInventoryItems->value,
            Permission::EditInventoryItems->value,
            Permission::DeleteInventoryItems->value,
            Permission::ViewTransactionLogs->value,
            Permission::CreateTransactionLogs->value,
            Permission::EditTransactionLogs->value,
            Permission::DeleteTransactionLogs->value,
            Permission::ViewPurchasedOrders->value,
            Permission::CreatePurchasedOrders->value,
            Permission::EditPurchasedOrders->value,
            Permission::DeletePurchasedOrders->value,
            // Checklist
            Permission::ViewChecklist->value,
            Permission::CreateChecklist->value,
            Permission::EditChecklist->value,
            Permission::DeleteChecklist->value,
            // Botcake
            Permission::ViewBotcakeSequences->value,
            Permission::ViewBotcakeSequenceMessages->value,
            Permission::ViewBotcakeFlows->value,
            // Finance
            Permission::ViewFinanceDashboard->value,
            Permission::ViewFinanceAccounts->value,
            Permission::CreateFinanceAccounts->value,
            Permission::EditFinanceAccounts->value,
            Permission::DeleteFinanceAccounts->value,
            Permission::ViewFinanceTransactions->value,
            Permission::CreateFinanceTransactions->value,
            Permission::EditFinanceTransactions->value,
            Permission::DeleteFinanceTransactions->value,
            Permission::ViewFinanceRemittances->value,
            Permission::CreateFinanceRemittances->value,
            Permission::EditFinanceRemittances->value,
            Permission::DeleteFinanceRemittances->value,
            // Pancake
            Permission::ViewCourierShipments->value,
            Permission::ImportCourierShipments->value,
            // Botcake
            Permission::ViewBotcakeSequences->value,
            Permission::ViewBotcakeSequenceMessages->value,
            Permission::ViewBotcakeFlows->value,
            // Settings
            Permission::EditWorkspaceSettings->value,
            Permission::ManageApiKeys->value,
        ];

        $permissions = PermissionModel::whereIn('name', $viewPermissions)->get();

        if ($permissions->isEmpty()) {
            $this->error('No permissions found in the system.');

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

        $this->info("\nGranted {$attached} new permissions to {$user->name} ({$userEmail}) in {$workspace->name}");

        return 0;
    }
}
