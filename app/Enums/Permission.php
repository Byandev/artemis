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
    case DeleteRoles = 'Archive Roles';
    case ManageRolePermissions = 'Manage Role Permissions';

    // Pages
    case ViewPages = 'View Pages';
    case CreatePages = 'Create Pages';
    case EditPages = 'Edit Pages';
    case ArchivePages = 'Archive Pages';
    case RefreshPages = 'Refresh Pages';

    // Shops
    case ViewShops = 'View Shops';
    case RefreshShops = 'Refresh Shops';

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
    case ViewParcelJourneyTemplates = 'View Parcel Journey Templates';
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

    // Checklist
    case ViewChecklist = 'View Checklist';
    case CreateChecklist = 'Create Checklist';
    case EditChecklist = 'Edit Checklist';
    case DeleteChecklist = 'Delete Checklist';

    // Finance
    case ViewFinanceDashboard = 'View Finance Dashboard';
    case ViewFinanceAccounts = 'View Finance Accounts';
    case CreateFinanceAccounts = 'Create Finance Accounts';
    case EditFinanceAccounts = 'Edit Finance Accounts';
    case DeleteFinanceAccounts = 'Delete Finance Accounts';
    case ViewFinanceTransactions = 'View Finance Transactions';
    case CreateFinanceTransactions = 'Create Finance Transactions';
    case EditFinanceTransactions = 'Edit Finance Transactions';
    case DeleteFinanceTransactions = 'Delete Finance Transactions';
    case ViewFinanceRemittances = 'View Finance Remittances';
    case CreateFinanceRemittances = 'Create Finance Remittances';
    case EditFinanceRemittances = 'Edit Finance Remittances';
    case DeleteFinanceRemittances = 'Delete Finance Remittances';

    // Pancake
    case ViewCourierShipments = 'View Courier Shipments';
    case ImportCourierShipments = 'Import Courier Shipments';

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

            self::ViewShops,
            self::RefreshShops => 'Shops',

            self::ViewProducts,
            self::CreateProducts,
            self::EditProducts,
            self::DeleteProducts => 'Products',

            self::ViewTeams,
            self::CreateTeams,
            self::EditTeams,
            self::DeleteTeams => 'Teams',

            self::ViewRtsAnalytics,
            self::ViewParcelJourneyTemplates,
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

            self::ViewChecklist,
            self::CreateChecklist,
            self::EditChecklist,
            self::DeleteChecklist => 'Checklist',

            self::ViewFinanceDashboard,
            self::ViewFinanceAccounts,
            self::CreateFinanceAccounts,
            self::EditFinanceAccounts,
            self::DeleteFinanceAccounts,
            self::ViewFinanceTransactions,
            self::CreateFinanceTransactions,
            self::EditFinanceTransactions,
            self::DeleteFinanceTransactions,
            self::ViewFinanceRemittances,
            self::CreateFinanceRemittances,
            self::EditFinanceRemittances,
            self::DeleteFinanceRemittances => 'Finance',

            self::ViewCourierShipments,
            self::ImportCourierShipments => 'Pancake',

            self::EditWorkspaceSettings,
            self::ManageApiKeys => 'Settings',
        };
    }
}
