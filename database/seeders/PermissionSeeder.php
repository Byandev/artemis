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
            ['category' => 'Members', 'name' => 'Remove Members'],
            ['category' => 'Members', 'name' => 'Edit Member Roles'],

            // Roles
            ['category' => 'Roles', 'name' => 'View Roles'],
            ['category' => 'Roles', 'name' => 'Create Roles'],
            ['category' => 'Roles', 'name' => 'Edit Roles'],
            ['category' => 'Roles', 'name' => 'Delete Roles'],
            ['category' => 'Roles', 'name' => 'Manage Role Permissions'],

            // Orders
            ['category' => 'Orders', 'name' => 'View Orders'],
            ['category' => 'Orders', 'name' => 'Export Orders'],

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

            // Inventory
            ['category' => 'Inventory', 'name' => 'View Inventory'],
            ['category' => 'Inventory', 'name' => 'Manage Inventory Items'],
            ['category' => 'Inventory', 'name' => 'Manage Transaction Logs'],
            ['category' => 'Inventory', 'name' => 'Manage Purchased Orders'],

            // Reports
            ['category' => 'Reports', 'name' => 'View Reports'],
            ['category' => 'Reports', 'name' => 'Export Reports'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['category' => $permission['category'], 'name' => $permission['name']]
            );
        }
    }
}
