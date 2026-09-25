<?php

namespace Modules\Products\Database\Seeders;

use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Modules\Products\Models\ProductForm;

class ProductFormSeeder extends Seeder
{
    /**
     * The delivery formats a workspace starts with. Sizes/variants are left to
     * the page — a form's sizes depend on what is actually being packed.
     *
     * @var list<string>
     */
    public const FORMS = [
        'Oil',
        'Spray',
        'Patch',
        'Inhaler',
        'Cream',
        'Balm',
        'Juice',
        'Coffee',
        'Gel',
        'Seeds',
    ];

    /**
     * Seeds every workspace with the products module switched on.
     *
     * Idempotent: a form already there is left as it is, including the sizes
     * filed under it, so re-running never disturbs a catalog somebody has
     * since edited.
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
            foreach (self::FORMS as $name) {
                $form = ProductForm::firstOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'name' => $name,
                    ],
                    // Only applied to forms this creates: one somebody has
                    // already described keeps their wording.
                    ['packshot_description' => ProductForm::DEFAULT_PACKSHOT_DESCRIPTIONS[mb_strtolower($name)] ?? null],
                );

                if ($form->wasRecentlyCreated) {
                    $added++;
                }
            }
        }

        $this->command?->info(sprintf(
            'Product forms: %d added across %d workspace(s).',
            $added,
            $workspaces->count(),
        ));
    }
}
