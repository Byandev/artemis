<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create the test user
        $testUser = User::where('email', 'test@example.com')->first();

        if (!$testUser) {
            $this->command->warn('Test user not found. Please run DatabaseSeeder first.');
            return;
        }

        // Get the first workspace for the test user, or create one
        $workspace = Workspace::where('owner_id', $testUser->id)->first();

        if (!$workspace) {
            $workspace = Workspace::factory()->forOwner($testUser)->create();
            $this->command->info("Created workspace: {$workspace->name}");
        }

        // Create additional users to be page owners
        $owners = User::factory(3)->create();
        
        // Add owners to the workspace as members
        foreach ($owners as $owner) {
            $workspace->addMember($owner, 'member');
        }
        $this->command->info("Created 3 additional users as workspace members");

        // Create shops for the workspace
        $shops = Shop::factory(3)->forWorkspace($workspace)->create();
        $this->command->info("Created 3 shops");

        // Create active pages
        foreach ($shops as $shop) {
            // Create 2-4 active pages per shop
            $pageCount = rand(2, 4);
            
            for ($i = 0; $i < $pageCount; $i++) {
                $owner = $owners->random();
                
                Page::factory()
                    ->forShop($shop)
                    ->forOwner($owner)
                    ->create();
            }
        }
        $this->command->info("Created active pages for each shop");

        // Create some archived pages
        $archivedCount = rand(2, 4);
        for ($i = 0; $i < $archivedCount; $i++) {
            $shop = $shops->random();
            $owner = $owners->random();
            
            Page::factory()
                ->forShop($shop)
                ->forOwner($owner)
                ->archived()
                ->create([
                    'name' => "Archived Page {$i}",
                ]);
        }
        $this->command->info("Created {$archivedCount} archived pages");

        // Summary
        $totalPages = Page::where('workspace_id', $workspace->id)->count();
        $activePages = Page::where('workspace_id', $workspace->id)->withoutTrashed()->count();
        $archivedPages = Page::where('workspace_id', $workspace->id)->onlyTrashed()->count();

        $this->command->info("---");
        $this->command->info("Summary for workspace '{$workspace->name}':");
        $this->command->info("  Total Pages: {$totalPages}");
        $this->command->info("  Active Pages: {$activePages}");
        $this->command->info("  Archived Pages: {$archivedPages}");
        $this->command->info("  Shops: " . $shops->count());
        $this->command->info("  Owners: " . ($owners->count() + 1));
    }
}
