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
    DeleteRoles: 'Delete Roles',
    ManageRolePermissions: 'Manage Role Permissions',

    // Pages
    ViewPages: 'View Pages',
    CreatePages: 'Create Pages',
    EditPages: 'Edit Pages',
    ArchivePages: 'Archive Pages',
    RefreshPages: 'Refresh Pages',

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

    // RTS
    ViewRtsAnalytics: 'View RTS Analytics',
    ManageParcelJourneyTemplates: 'Manage Parcel Journey Templates',

    // CSR
    ViewCsrManagement: 'View CSR Management',
    EditCsrEmployees: 'Edit CSR Employees',
    ViewCsrAnalytics: 'View CSR Analytics',

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

    // Settings
    EditWorkspaceSettings: 'Edit Workspace Settings',
    ManageApiKeys: 'Manage API Keys',
} as const;

export type PermissionName = (typeof PERMISSIONS)[keyof typeof PERMISSIONS];
