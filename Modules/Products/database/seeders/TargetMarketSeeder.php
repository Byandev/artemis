<?php

namespace Modules\Products\Database\Seeders;

use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Modules\Products\Models\TargetMarket;

class TargetMarketSeeder extends Seeder
{
    /**
     * The categories a workspace starts with. Sub categories are left to the
     * page — these are the top level only.
     *
     * @var list<string>
     */
    public const CATEGORIES = [
        'Cardiovascular',
        'Metabolic & Endocrine',
        'Respiratory',
        'Infectious Diseases',
        'Gastrointestinal',
        'Neurological',
        'Musculoskeletal',
        'Oncology',
        "Women's Health",
        "Men's Health",
    ];

    /**
     * The sub categories under each category, keyed by the category's name.
     * Only the ones we have been given — a category missing here is seeded
     * with no children, and they get filed on the page.
     *
     * @var array<string, list<string>>
     */
    public const SUB_CATEGORIES = [
        'Cardiovascular' => [
            'Hypertension',
            'Coronary Artery Disease',
            'Heart Failure',
            'Heart Attack',
            'Arrhythmia',
        ],
        'Musculoskeletal' => [
            'Sports Injuries',
            'Back Pain',
            'Osteoporosis',
            'Arthritis',
        ],
    ];

    /**
     * Seeds every workspace with the products module switched on.
     *
     * Idempotent: a category already there is left as it is, including any sub
     * categories filed under it, so re-running this never disturbs a tree
     * somebody has since edited.
     */
    public function run(): void
    {
        $workspaces = Workspace::where('products_module_enabled', true)->get();

        if ($workspaces->isEmpty()) {
            $this->command?->warn('No workspace has the Products module enabled — nothing to seed.');

            return;
        }

        $added = 0;

        foreach ($workspaces as $workspace) {
            foreach (self::CATEGORIES as $position => $name) {
                $market = TargetMarket::firstOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'parent_id' => null,
                        'name' => $name,
                    ],
                    // Only applied to rows this creates: a category already
                    // there keeps whatever position it was moved to.
                    ['position' => $position],
                );

                if ($market->wasRecentlyCreated) {
                    $added++;
                }

                foreach (self::SUB_CATEGORIES[$name] ?? [] as $childPosition => $childName) {
                    $child = TargetMarket::firstOrCreate(
                        [
                            'workspace_id' => $workspace->id,
                            'parent_id' => $market->id,
                            'name' => $childName,
                        ],
                        ['position' => $childPosition],
                    );

                    if ($child->wasRecentlyCreated) {
                        $added++;
                    }
                }
            }
        }

        $this->command?->info(sprintf(
            'Target markets: %d row(s) added across %d workspace(s).',
            $added,
            $workspaces->count(),
        ));
    }
}
