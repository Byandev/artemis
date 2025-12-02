<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;

class SeedMorePages extends Command
{
    protected $signature = 'seed:pages {count=25} {--archived=5}';
    protected $description = 'Seed more pages for testing pagination';

    public function handle()
    {
        $count = (int) $this->argument('count');
        $archivedCount = (int) $this->option('archived');
        
        $workspace = Workspace::first();
        
        if (!$workspace) {
            $this->error('No workspace found. Run db:seed first.');
            return 1;
        }

        $shops = Shop::where('workspace_id', $workspace->id)->get();
        
        if ($shops->isEmpty()) {
            $this->info('Creating shops...');
            $shops = Shop::factory(3)->forWorkspace($workspace)->create();
        }

        $owners = User::whereIn('id', $workspace->users()->pluck('user_id'))->get();
        
        if ($owners->isEmpty()) {
            $this->info('Creating owners...');
            $owners = User::factory(3)->create();
            foreach ($owners as $owner) {
                $workspace->addMember($owner, 'member');
            }
        }

        $this->info("Creating {$count} active pages...");
        
        for ($i = 0; $i < $count; $i++) {
            Page::factory()
                ->forWorkspace($workspace)
                ->forShop($shops->random())
                ->forOwner($owners->random())
                ->create();
        }

        $this->info("Creating {$archivedCount} archived pages...");
        
        for ($i = 0; $i < $archivedCount; $i++) {
            Page::factory()
                ->forWorkspace($workspace)
                ->forShop($shops->random())
                ->forOwner($owners->random())
                ->archived()
                ->create(['name' => "Archived Test Page {$i}"]);
        }

        $activeCount = Page::where('workspace_id', $workspace->id)->active()->count();
        $totalArchived = Page::where('workspace_id', $workspace->id)->archived()->count();

        $this->info('---');
        $this->info("Total Active Pages: {$activeCount}");
        $this->info("Total Archived Pages: {$totalArchived}");
        $this->info("Pagination will show at: 10+ items per tab");

        return 0;
    }
}
