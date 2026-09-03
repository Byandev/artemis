<?php

namespace Database\Seeders;

use App\Enums\DailyTrackerCadence;
use App\Models\DailyTrackerItem;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

/**
 * The deliverables a Sales & Marketing workspace starts with.
 *
 * Items are workspace-scoped rows rather than hard-coded questions so a
 * workspace can retire or reword one without a deploy. This seeder only fills an
 * empty tracker — it never touches a workspace that already has items, so
 * re-running it cannot resurrect something a workspace deliberately removed.
 */
class DailyTrackerItemSeeder extends Seeder
{
    /**
     * Ordered as they appear on the board. `position` is spaced by 10 so an item
     * can be slotted between two others without renumbering the rest.
     *
     * @var array<int, array{category: string, label: string, cadence: DailyTrackerCadence, tags: array<int, string>}>
     */
    private const DEFAULTS = [
        [
            'category' => 'Self care',
            'label' => 'Were you able to finish your extreme self care - (meditation, learning, and movement)?',
            'cadence' => DailyTrackerCadence::Daily,
            'tags' => [],
        ],
        [
            'category' => 'Ad spend',
            'label' => 'Is your ad spend in ERP updated and matching Ads Manager?',
            'cadence' => DailyTrackerCadence::Daily,
            'tags' => ['ERP'],
        ],
        [
            'category' => 'Ad spend',
            'label' => 'Do you have any pages below 3.0 ROAS for 3 consecutive days? (Check, if none).',
            'cadence' => DailyTrackerCadence::Daily,
            'tags' => [],
        ],
        [
            'category' => 'Trackers',
            'label' => 'Is GoTyme balance updated as of today?',
            'cadence' => DailyTrackerCadence::Daily,
            'tags' => ['GOTYME'],
        ],
        [
            'category' => 'Trackers',
            'label' => 'Is your Testing Creative Tracker updated?',
            'cadence' => DailyTrackerCadence::Daily,
            'tags' => ['CREATIVES'],
        ],
        [
            'category' => 'Trackers',
            'label' => "From yesterday's commitment, were the committed creatives actually launched?",
            'cadence' => DailyTrackerCadence::Daily,
            'tags' => [],
        ],
        [
            'category' => 'Team',
            'label' => 'Were you able to huddle your CSR team today?',
            'cadence' => DailyTrackerCadence::Daily,
            'tags' => [],
        ],
        [
            'category' => 'Orders',
            'label' => 'Were you able to fetch orders for univoice today at 9AM, 12NN, 3PM, and 6PM?',
            'cadence' => DailyTrackerCadence::Daily,
            'tags' => [],
        ],
        [
            'category' => 'Weekly',
            // The cadence renders its own WEEKLY pill, so it is not repeated here.
            'label' => 'Were you able to RDP, cost, and test new item? (Tick on a weekly basis).',
            'cadence' => DailyTrackerCadence::Weekly,
            'tags' => ['RDP-BFM'],
        ],
    ];

    public function run(): void
    {
        Workspace::query()
            ->where('sales_marketing_dashboard_module_enabled', true)
            ->whereDoesntHave('dailyTrackerItems')
            ->each(fn (Workspace $workspace) => $this->seedWorkspace($workspace));
    }

    /**
     * Give one workspace the default deliverables. Public so a workspace can be
     * filled in from a tinker session or a follow-up migration.
     */
    public function seedWorkspace(Workspace $workspace): void
    {
        foreach (self::DEFAULTS as $index => $default) {
            DailyTrackerItem::create([
                'workspace_id' => $workspace->id,
                'category' => $default['category'],
                'label' => $default['label'],
                'cadence' => $default['cadence'],
                'tags' => $default['tags'],
                'position' => ($index + 1) * 10,
                'active' => true,
            ]);
        }
    }
}
