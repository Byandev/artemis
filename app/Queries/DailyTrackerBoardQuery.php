<?php

namespace App\Queries;

use App\Models\DailyTrackerCompletion;
use App\Models\DailyTrackerItem;
use App\Models\User;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the Daily Tracker board for one day: who is on it, what they owe, and
 * which of those boxes are already ticked.
 *
 * Three reads, flat payload. The board deliberately stops at "member X has
 * completed items [1, 4, 9]" — the page derives counts, percentages, per-category
 * tallies and the team matrix from that one map, so a member's progress has a
 * single definition rather than one per view.
 *
 * The roster is the workspace's members narrowed by team visibility, the same
 * rule the rest of Sales & Marketing uses: a scoped user sees their team(s), and
 * the "viewing as team" switcher narrows everyone to one team.
 *
 * Completions are keyed by the period a tick satisfies, not by the day it was
 * made (see App\Enums\DailyTrackerCadence) — so one equality lookup covers both
 * a daily item and a weekly one that was ticked earlier in the week.
 */
class DailyTrackerBoardQuery
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly User $viewer,
        private readonly CarbonImmutable $date,
    ) {}

    /**
     * @return array{
     *     members: array<int, array{id: int, name: string}>,
     *     items: array<int, array{id: int, category: string, label: string, cadence: string, tags: array<int, string>}>,
     *     completions: array<int, array<int, int>>
     * }
     */
    public function get(): array
    {
        $members = $this->members();
        $items = $this->items();

        return [
            'members' => $members->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])->all(),
            'items' => $items->map(fn (DailyTrackerItem $item) => [
                'id' => $item->id,
                'category' => $item->category,
                'label' => $item->label,
                'cadence' => $item->cadence->value,
                'tags' => $item->tags ?? [],
            ])->all(),
            'completions' => $this->completions($members->pluck('id')->all(), $items),
        ];
    }

    /**
     * The members whose rows this viewer may see, ordered the way the roster
     * lists them.
     *
     * @return Collection<int, User>
     */
    private function members(): Collection
    {
        return $this->workspace->users()
            ->when(
                TeamVisibility::shouldScope($this->viewer, $this->workspace),
                fn ($query) => $query->whereIn('users.id', function ($sub) {
                    $sub->select('user_id')
                        ->from('team_user')
                        ->whereIn('team_id', TeamVisibility::scopeTeamIds($this->viewer, $this->workspace) ?? []);
                }),
            )
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, DailyTrackerItem>
     */
    private function items(): Collection
    {
        return DailyTrackerItem::query()
            ->where('workspace_id', $this->workspace->id)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Completed item ids per member, as `[user_id => [item_id, …]]`.
     *
     * Members with nothing ticked are present with an empty list so the page can
     * read the map without a fallback.
     *
     * @param  array<int, int>  $memberIds
     * @param  Collection<int, DailyTrackerItem>  $items
     * @return array<int, array<int, int>>
     */
    private function completions(array $memberIds, Collection $items): array
    {
        $completed = array_fill_keys($memberIds, []);

        if ($memberIds === [] || $items->isEmpty()) {
            return $completed;
        }

        // The period each item's tick is filed under. Daily items land on the
        // day, weekly ones on its Monday — usually two distinct dates in total.
        $periodByItem = $items->mapWithKeys(fn (DailyTrackerItem $item) => [
            $item->id => $item->cadence->periodStart($this->date)->toDateString(),
        ]);

        DailyTrackerCompletion::query()
            ->where('workspace_id', $this->workspace->id)
            ->whereIn('user_id', $memberIds)
            ->whereIn('daily_tracker_item_id', $periodByItem->keys())
            ->whereIn('tracked_on', $periodByItem->values()->unique())
            ->get(['daily_tracker_item_id', 'user_id', 'tracked_on'])
            // A weekly item's row can come back under the day's date (and vice
            // versa) when its cadence changed after the tick — keep only the
            // rows filed under the period the item asks for today.
            ->filter(fn (DailyTrackerCompletion $row) => $row->tracked_on->toDateString() === $periodByItem[$row->daily_tracker_item_id])
            ->each(function (DailyTrackerCompletion $row) use (&$completed) {
                $completed[$row->user_id][] = $row->daily_tracker_item_id;
            });

        return $completed;
    }
}
