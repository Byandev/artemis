export const PERMISSIONS = {
    // Members
    ViewMembers: 'View Members',
    InviteMembers: 'Invite Members',
    EditMembers: 'Edit Members',
    RemoveMembers: 'Remove Members',
    ResetMemberPassword: 'Reset Member Password',

    // Roles
    ViewRoles: 'View Roles',
    CreateRoles: 'Create Roles',
    EditRoles: 'Edit Roles',
    DeleteRoles: 'Archive Roles',
    ManageRolePermissions: 'Manage Role Permissions',

    // Pages
    ViewPages: 'View Pages',
    CreatePages: 'Create Pages',
    EditPages: 'Edit Pages',
    ArchivePages: 'Archive Pages',
    RefreshPages: 'Refresh Pages',
    UpdatePageBudget: 'Update Page Budget',

    // Shops
    ViewShops: 'View Shops',
    RefreshShops: 'Refresh Shops',

    // Products
    ViewProducts: 'View Products',
    CreateProducts: 'Create Products',
    EditProducts: 'Edit Products',
    DeleteProducts: 'Delete Products',

    // Teams
    ViewTeams: 'View Teams',
    CreateTeams: 'Create Teams',
    EditTeams: 'Edit Teams',
    DeleteTeams: 'Delete Teams',
    ManageSchedule: 'Manage Schedule',

    // RTS
    ViewRtsAnalytics: 'View RTS Analytics',
    ViewRtsAiChat: 'View RTS AI Chat',
    ViewRmoManagement: 'View RMO Management',
    ViewParcelJourneyTemplates: 'View Parcel Journey Templates',
    ManageParcelJourneyTemplates: 'Manage Parcel Journey Templates',

    // CSR
    ViewCsrManagement: 'View CSR Management',
    EditCsrEmployees: 'Edit CSR Employees',
    ViewCsrAnalytics: 'View CSR Analytics',
    ViewLeaderboards: 'View Leaderboards',

    // Inventory
    ViewInventoryItems: 'View Inventory Items',
    CreateInventoryItems: 'Create Inventory Items',
    EditInventoryItems: 'Edit Inventory Items',
    DeleteInventoryItems: 'Delete Inventory Items',
    ViewTransactionLogs: 'View Transaction Logs',
    CreateTransactionLogs: 'Create Transaction Logs',
    EditTransactionLogs: 'Edit Transaction Logs',
    DeleteTransactionLogs: 'Delete Transaction Logs',
    ViewPurchasedOrders: 'View Purchased Orders',
    CreatePurchasedOrders: 'Create Purchased Orders',
    EditPurchasedOrders: 'Edit Purchased Orders',
    DeletePurchasedOrders: 'Delete Purchased Orders',

    // Checklist
    ViewChecklist: 'View Checklist',
    CreateChecklist: 'Create Checklist',
    EditChecklist: 'Edit Checklist',
    DeleteChecklist: 'Delete Checklist',

    // Botcake
    ViewBotcakeSequences: 'View Botcake Sequences',
    ViewBotcakeSequenceMessages: 'View Botcake Sequence Messages',
    ViewBotcakeFlows: 'View Botcake Flows',

    // Finance
    ViewFinanceDashboard: 'View Finance Dashboard',
    ViewFinanceAccounts: 'View Finance Accounts',
    CreateFinanceAccounts: 'Create Finance Accounts',
    EditFinanceAccounts: 'Edit Finance Accounts',
    DeleteFinanceAccounts: 'Delete Finance Accounts',
    ViewFinanceTransactions: 'View Finance Transactions',
    CreateFinanceTransactions: 'Create Finance Transactions',
    EditFinanceTransactions: 'Edit Finance Transactions',
    DeleteFinanceTransactions: 'Delete Finance Transactions',
    ViewFinanceRemittances: 'View Finance Remittances',
    CreateFinanceRemittances: 'Create Finance Remittances',
    EditFinanceRemittances: 'Edit Finance Remittances',
    DeleteFinanceRemittances: 'Delete Finance Remittances',

    // Page Daily Budget Records
    ViewPageDailyBudgetRecords: 'View Page Daily Budget Records',
    CreatePageDailyBudgetRecords: 'Create Page Daily Budget Records',
    EditPageDailyBudgetRecords: 'Edit Page Daily Budget Records',
    DeletePageDailyBudgetRecords: 'Delete Page Daily Budget Records',

    // Creatives
    ViewCreatives: 'View Creatives',
    CreateCreatives: 'Create Creatives',
    EditCreatives: 'Edit Creatives',
    DeleteCreatives: 'Delete Creatives',
    ReviewCreatives: 'Review Creatives',
    UpdateCreativeStatus: 'Update Creative Status',

    // Meta Ads
    ViewMetaAds: 'View Meta Ads',
    ManageMetaAdsAccounts: 'Manage Meta Ads Accounts',
    ViewOptimizationRules: 'View Optimization Rules',
    ManageOptimizationRules: 'Manage Optimization Rules',
    ApproveOptimizationRules: 'Approve Optimization Rules',

    // Dashboards
    ViewMainDashboard: 'View Main Dashboard',
    ViewSalesMarketingDashboard: 'View Sales & Marketing Dashboard',
    ViewVideoEditorDashboard: 'View Video Editor Dashboard',
    ViewCsrDashboard: 'View CSR Dashboard',

    // Settings
    EditWorkspaceSettings: 'Edit Workspace Settings',
    ManageApiKeys: 'Manage API Keys',
} as const;

export type PermissionName = (typeof PERMISSIONS)[keyof typeof PERMISSIONS];
