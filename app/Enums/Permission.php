<?php

namespace App\Enums;

enum Permission: string
{
    // Members
    case ViewMembers = 'View Members';
    case InviteMembers = 'Invite Members';
    case EditMembers = 'Edit Members';
    case RemoveMembers = 'Remove Members';
    case ResetMemberPassword = 'Reset Member Password';

    // Roles
    case ViewRoles = 'View Roles';
    case CreateRoles = 'Create Roles';
    case EditRoles = 'Edit Roles';
    case DeleteRoles = 'Delete Roles';
    case ManageRolePermissions = 'Manage Role Permissions';

    // Pages
    case ViewPages = 'View Pages';
    case CreatePages = 'Create Pages';
    case EditPages = 'Edit Pages';
    case ArchivePages = 'Archive Pages';
    case RefreshPages = 'Refresh Pages';

    // Products
    case ViewProducts = 'View Products';
    case CreateProducts = 'Create Products';
    case EditProducts = 'Edit Products';
    case DeleteProducts = 'Delete Products';

    // Teams
    case ViewTeams = 'View Teams';
    case CreateTeams = 'Create Teams';
    case EditTeams = 'Edit Teams';
    case DeleteTeams = 'Delete Teams';

    // RTS
    case ViewRtsAnalytics = 'View RTS Analytics';
    case ManageParcelJourneyTemplates = 'Manage Parcel Journey Templates';

    // CSR
    case ViewCsrManagement = 'View CSR Management';
    case EditCsrEmployees = 'Edit CSR Employees';
    case ViewCsrAnalytics = 'View CSR Analytics';

    // Inventory
    case ViewInventoryItems = 'View Inventory Items';
    case CreateInventoryItems = 'Create Inventory Items';
    case EditInventoryItems = 'Edit Inventory Items';
    case DeleteInventoryItems = 'Delete Inventory Items';
    case ViewTransactionLogs = 'View Transaction Logs';
    case CreateTransactionLogs = 'Create Transaction Logs';
    case EditTransactionLogs = 'Edit Transaction Logs';
    case DeleteTransactionLogs = 'Delete Transaction Logs';
    case ViewPurchasedOrders = 'View Purchased Orders';
    case CreatePurchasedOrders = 'Create Purchased Orders';
    case EditPurchasedOrders = 'Edit Purchased Orders';
    case DeletePurchasedOrders = 'Delete Purchased Orders';

    // Settings
    case EditWorkspaceSettings = 'Edit Workspace Settings';
    case ManageApiKeys = 'Manage API Keys';

    public function category(): string
    {
        return match ($this) {
            self::ViewMembers,
            self::InviteMembers,
            self::EditMembers,
            self::RemoveMembers,
            self::ResetMemberPassword => 'Members',

            self::ViewRoles,
            self::CreateRoles,
            self::EditRoles,
            self::DeleteRoles,
            self::ManageRolePermissions => 'Roles',

            self::ViewPages,
            self::CreatePages,
            self::EditPages,
            self::ArchivePages,
            self::RefreshPages => 'Pages',

            self::ViewProducts,
            self::CreateProducts,
            self::EditProducts,
            self::DeleteProducts => 'Products',

            self::ViewTeams,
            self::CreateTeams,
            self::EditTeams,
            self::DeleteTeams => 'Teams',

            self::ViewRtsAnalytics,
            self::ManageParcelJourneyTemplates => 'RTS',

            self::ViewCsrManagement,
            self::EditCsrEmployees,
            self::ViewCsrAnalytics => 'CSR',

            self::ViewInventoryItems,
            self::CreateInventoryItems,
            self::EditInventoryItems,
            self::DeleteInventoryItems,
            self::ViewTransactionLogs,
            self::CreateTransactionLogs,
            self::EditTransactionLogs,
            self::DeleteTransactionLogs,
            self::ViewPurchasedOrders,
            self::CreatePurchasedOrders,
            self::EditPurchasedOrders,
            self::DeletePurchasedOrders => 'Inventory',

            self::EditWorkspaceSettings,
            self::ManageApiKeys => 'Settings',
        };
    }
}
