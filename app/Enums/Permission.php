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
    case UpdatePageBudget = 'Update Page Budget';

    // Shops
    case ViewShops = 'View Shops';
    case CreateShops = 'Create Shops';
    case EditShops = 'Edit Shops';
    case RefreshShops = 'Refresh Shops';
    case DeleteShops = 'Delete Shops';

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
    case ManageSchedule = 'Manage Schedule';

    // Ad Spend Goals
    case ViewAdSpendGoals = 'View Ad Spend Goals';
    case ManageAdSpendGoals = 'Manage Ad Spend Goals';

    // Departments
    case ViewDepartments = 'View Departments';
    case CreateDepartments = 'Create Departments';
    case EditDepartments = 'Edit Departments';
    case DeleteDepartments = 'Delete Departments';

    // RTS
    case ViewRtsAnalytics = 'View RTS Analytics';
    case ViewRtsAiChat = 'View RTS AI Chat';
    case ViewRmoManagement = 'View RMO Management';
    case ManageRmoSettings = 'Manage RMO Settings';
    case ManageRmoNotifications = 'Manage RMO Notifications';
    case ViewParcelJourneyTemplates = 'View Parcel Journey Templates';
    case ManageParcelJourneyTemplates = 'Manage Parcel Journey Templates';

    // CSR
    case ViewCsrManagement = 'View CSR Management';
    case EditCsrEmployees = 'Edit CSR Employees';
    case ViewCsrAnalytics = 'View CSR Analytics';
    case ViewLeaderboards = 'View Leaderboards';

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

    // Gencys ERP
    case ViewDailySalesTracker = 'View Daily Sales Tracker';
    case ViewGencysInterns = 'View Gencys Interns';
    case ViewGencysInternDailyRecords = 'View Gencys Intern Daily Records';
    case ViewGencysPages = 'View Gencys Pages';
    case ViewGencysSync = 'View Gencys Sync';
    case ViewUnitCode = 'View Unit Code';
    case CreateUnitCode = 'Create Unit Code';
    case EditUnitCode = 'Edit Unit Code';
    case DeleteUnitCode = 'Delete Unit Code';

    // Checklist
    case ViewChecklist = 'View Checklist';
    case CreateChecklist = 'Create Checklist';
    case EditChecklist = 'Edit Checklist';
    case DeleteChecklist = 'Delete Checklist';

    // Botcake
    case ViewBotcakeSequences = 'View Botcake Sequences';
    case ViewBotcakeSequenceMessages = 'View Botcake Sequence Messages';
    case ViewBotcakeFlows = 'View Botcake Flows';

    // SMS
    case ViewSims = 'View SIMs';
    case SendSms = 'Send SMS';
    case ViewSmsOutbox = 'View SMS Outbox';

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
    case ViewFinanceRequestFunds = 'View Finance Request Funds';
    case CreateFinanceRequestFunds = 'Create Finance Request Funds';
    case EditFinanceRequestFunds = 'Edit Finance Request Funds';
    case DeleteFinanceRequestFunds = 'Delete Finance Request Funds';
    case ApproveFinanceRequestFunds = 'Approve Finance Request Funds';

    // Pancake
    case ViewOrders = 'View Orders';
    case ViewCourierShipments = 'View Courier Shipments';
    case ImportCourierShipments = 'Import Courier Shipments';

    // Page Daily Budget Records
    case ViewPageDailyBudgetRecords = 'View Page Daily Budget Records';
    case CreatePageDailyBudgetRecords = 'Create Page Daily Budget Records';
    case EditPageDailyBudgetRecords = 'Edit Page Daily Budget Records';
    case DeletePageDailyBudgetRecords = 'Delete Page Daily Budget Records';

    // Creatives
    case ViewCreatives = 'View Creatives';
    case CreateCreatives = 'Create Creatives';
    case EditCreatives = 'Edit Creatives';
    case DeleteCreatives = 'Delete Creatives';
    case ReviewCreatives = 'Review Creatives';
    case UpdateCreativeStatus = 'Update Creative Status';

    // Meta Ads
    case ViewMetaAds = 'View Meta Ads';
    case ManageMetaAdsAccounts = 'Manage Meta Ads Accounts';
    case ViewOptimizationRules = 'View Optimization Rules';
    case ManageOptimizationRules = 'Manage Optimization Rules';
    case ApproveOptimizationRules = 'Approve Optimization Rules';

    // Dashboards
    case ViewMainDashboard = 'View Main Dashboard';
    case ViewVideoEditorDashboard = 'View Video Editor Dashboard';
    case ViewCsrDashboard = 'View CSR Dashboard';

    // Sales & Marketing
    //
    // One per page. This was a single "View Sales & Marketing Dashboard" while
    // the five were tabs of one screen; splitting the screen into five sibling
    // pages splits the grant with it, so a role can be given the Daily Report
    // without also being given everyone's sales targets.
    //
    // Only three are new. Ad Spend Goals and Ad Spent Summary already had their
    // own permissions from when they were standalone pages — those keep their
    // existing names and categories rather than being duplicated here.
    case ViewSalesMarketingDailyReport = 'View S&M Daily Report';
    case ViewPageRoasTracker = 'View Page ROAS Tracker';
    case ViewSalesTargets = 'View Sales Targets';
    case ViewNewCreativesTracker = 'View New Creatives Tracker';

    // Settings
    case EditWorkspaceSettings = 'Edit Workspace Settings';
    case ManageApiKeys = 'Manage API Keys';
    case ManageDiscordNotifications = 'Manage Discord Notifications';

    // Billing
    case ViewBillingSettings = 'View Billing Settings';
    case ManageBillingSettings = 'Manage Billing Settings';
    case ViewInvoices = 'View Invoices';

    // Courses
    case ViewCourses = 'View Courses';
    case CreateCourses = 'Create Courses';
    case EditCourses = 'Edit Courses';
    case DeleteCourses = 'Delete Courses';

    // Meta Ads
    case ConnectFbAccount = 'Connect FB Account';
    case ViewAdAccounts = 'View Ad Accounts';
    case ViewOptimizationLogs = 'View Optimization Logs';
    case ViewAdSpentSummary = 'View Adspent Summary';

    // Data Access
    case ViewAllWorkspaceData = 'View All Workspace Data';

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
            self::RefreshPages,
            self::UpdatePageBudget => 'Pages',

            self::ViewShops,
            self::CreateShops,
            self::EditShops,
            self::RefreshShops,
            self::DeleteShops => 'Shops',

            self::ViewProducts,
            self::CreateProducts,
            self::EditProducts,
            self::DeleteProducts => 'Products',

            self::ViewTeams,
            self::CreateTeams,
            self::EditTeams,
            self::DeleteTeams,
            self::ManageSchedule => 'Teams',

            self::ViewAdSpendGoals,
            self::ManageAdSpendGoals => 'Ad Spend Goals',

            self::ViewDepartments,
            self::CreateDepartments,
            self::EditDepartments,
            self::DeleteDepartments => 'Departments',

            self::ViewRtsAnalytics,
            self::ViewRtsAiChat,
            self::ViewRmoManagement,
            self::ManageRmoSettings,
            self::ManageRmoNotifications,
            self::ViewParcelJourneyTemplates,
            self::ManageParcelJourneyTemplates => 'RTS',

            self::ViewCsrManagement,
            self::EditCsrEmployees,
            self::ViewCsrAnalytics,
            self::ViewLeaderboards => 'CSR',

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

            self::ViewDailySalesTracker,
            self::ViewGencysInterns,
            self::ViewGencysInternDailyRecords,
            self::ViewGencysPages,
            self::ViewGencysSync,
            self::ViewUnitCode,
            self::CreateUnitCode,
            self::EditUnitCode,
            self::DeleteUnitCode => 'Gencys ERP',

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
            self::DeleteFinanceRemittances,
            self::ViewFinanceRequestFunds,
            self::CreateFinanceRequestFunds,
            self::EditFinanceRequestFunds,
            self::DeleteFinanceRequestFunds,
            self::ApproveFinanceRequestFunds => 'Finance',

            self::ViewOrders,
            self::ViewCourierShipments,
            self::ImportCourierShipments => 'Pancake',

            self::ViewCreatives,
            self::CreateCreatives,
            self::EditCreatives,
            self::DeleteCreatives,
            self::ReviewCreatives,
            self::UpdateCreativeStatus => 'Creatives',

            self::ViewBotcakeSequences,
            self::ViewBotcakeSequenceMessages,
            self::ViewBotcakeFlows => 'Botcake',

            self::ViewSims,
            self::SendSms,
            self::ViewSmsOutbox => 'SMS',

            self::ViewPageDailyBudgetRecords,
            self::CreatePageDailyBudgetRecords,
            self::EditPageDailyBudgetRecords,
            self::DeletePageDailyBudgetRecords => 'Page Daily Budget Records',

            self::ViewMainDashboard,
            self::ViewVideoEditorDashboard,
            self::ViewCsrDashboard => 'Dashboards',

            self::ViewSalesMarketingDailyReport,
            self::ViewPageRoasTracker,
            self::ViewSalesTargets,
            self::ViewNewCreativesTracker => 'Sales & Marketing',

            self::EditWorkspaceSettings,
            self::ManageApiKeys,
            self::ManageDiscordNotifications => 'Settings',

            self::ViewBillingSettings,
            self::ManageBillingSettings,
            self::ViewInvoices => 'Billing',

            self::ViewCourses,
            self::CreateCourses,
            self::EditCourses,
            self::DeleteCourses => 'Courses',

            self::ViewMetaAds,
            self::ConnectFbAccount,
            self::ViewAdAccounts,
            self::ManageMetaAdsAccounts,
            self::ViewOptimizationRules,
            self::ManageOptimizationRules,
            self::ApproveOptimizationRules,
            self::ViewOptimizationLogs,
            self::ViewAdSpentSummary => 'Meta Ads',

            self::ViewAllWorkspaceData => 'Data Access',
        };
    }
}
