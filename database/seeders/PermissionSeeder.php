<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Members
            ['category' => 'Members', 'name' => 'View Members'],
            ['category' => 'Members', 'name' => 'Invite Members'],
            ['category' => 'Members', 'name' => 'Edit Members'],
            ['category' => 'Members', 'name' => 'Remove Members'],
            ['category' => 'Members', 'name' => 'Reset Member Password'],

            // Roles
            ['category' => 'Roles', 'name' => 'View Roles'],
            ['category' => 'Roles', 'name' => 'Create Roles'],
            ['category' => 'Roles', 'name' => 'Edit Roles'],
            ['category' => 'Roles', 'name' => 'Delete Roles'],
            ['category' => 'Roles', 'name' => 'Manage Role Permissions'],

            // Pages
            ['category' => 'Pages', 'name' => 'View Pages'],
            ['category' => 'Pages', 'name' => 'Create Pages'],
            ['category' => 'Pages', 'name' => 'Edit Pages'],
            ['category' => 'Pages', 'name' => 'Archive Pages'],
            ['category' => 'Pages', 'name' => 'Refresh Pages'],

            // Products
            ['category' => 'Products', 'name' => 'View Products'],
            ['category' => 'Products', 'name' => 'Create Products'],
            ['category' => 'Products', 'name' => 'Edit Products'],
            ['category' => 'Products', 'name' => 'Delete Products'],

            // Teams
            ['category' => 'Teams', 'name' => 'View Teams'],
            ['category' => 'Teams', 'name' => 'Create Teams'],
            ['category' => 'Teams', 'name' => 'Edit Teams'],
            ['category' => 'Teams', 'name' => 'Delete Teams'],

            // RTS
            ['category' => 'RTS', 'name' => 'View RTS Analytics'],
            ['category' => 'RTS', 'name' => 'Manage Parcel Journey Templates'],

            // CSR
            ['category' => 'CSR', 'name' => 'View CSR Management'],
            ['category' => 'CSR', 'name' => 'Edit CSR Employees'],
            ['category' => 'CSR', 'name' => 'View CSR Analytics'],

            // Inventory
            ['category' => 'Inventory', 'name' => 'View Inventory Items'],
            ['category' => 'Inventory', 'name' => 'Create Inventory Items'],
            ['category' => 'Inventory', 'name' => 'Edit Inventory Items'],
            ['category' => 'Inventory', 'name' => 'Delete Inventory Items'],
            ['category' => 'Inventory', 'name' => 'View Transaction Logs'],
            ['category' => 'Inventory', 'name' => 'Create Transaction Logs'],
            ['category' => 'Inventory', 'name' => 'Edit Transaction Logs'],
            ['category' => 'Inventory', 'name' => 'Delete Transaction Logs'],
            ['category' => 'Inventory', 'name' => 'View Purchased Orders'],
            ['category' => 'Inventory', 'name' => 'Create Purchased Orders'],
            ['category' => 'Inventory', 'name' => 'Edit Purchased Orders'],
            ['category' => 'Inventory', 'name' => 'Delete Purchased Orders'],

            // Settings
            ['category' => 'Settings', 'name' => 'Edit Workspace Settings'],
            ['category' => 'Settings', 'name' => 'Manage API Keys'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']], // Unique identifier
                ['category' => $permission['category']]
            );
        }
    }
}