<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Enums\TaskPriority;
use Modules\TaskManagement\Enums\TaskStatusType;
use Modules\TaskManagement\Models\Task;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class TaskIndexTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_index_returns_only_tasks_from_spaces_the_user_can_see(): void
    {
        $user = User::factory()->create();
        $mine = $this->taskIn($this->listIn($this->spaceOwnedBy($user)));
        $shared = $this->taskIn($this->listIn($this->spaceWhereUserIs($user, SpaceRole::Viewer)));
        Task::factory()->create();

        $response = $this->actingAs($user)->getJson($this->tmRoute('tasks.index'));

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$mine->id, $shared->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson($this->tmRoute('tasks.index'))->assertUnauthorized();
    }

    public function test_index_is_paginated_and_reports_the_total(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        Task::factory()->count(5)->create([
            'task_list_id' => $list->id,
            'task_status_id' => $this->defaultStatusOf($list->space),
        ]);

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.index', ['per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_filters_by_space(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $wanted = $this->taskIn($this->listIn($space));
        $this->taskIn($this->listIn($this->spaceOwnedBy($user)));

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['filter' => ['space_id' => $space->id]])
        );

        $response->assertOk();
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_filters_by_folder(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $folder = $this->folderIn($space);
        $wanted = $this->taskIn($this->listIn($space, $folder));
        $this->taskIn($this->listIn($space));

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['filter' => ['folder_id' => $folder->id]])
        );

        $response->assertOk();
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_filters_by_list(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $list = $this->listIn($space);
        $wanted = $this->taskIn($list);
        $this->taskIn($this->listIn($space));

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['filter' => ['list_id' => $list->id]])
        );

        $response->assertOk();
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_filters_by_status_type(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $list = $this->listIn($space);
        $done = $space->statuses()->where('slug', 'done')->sole();
        $wanted = $this->taskIn($list, ['task_status_id' => $done->id]);
        $this->taskIn($list);

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['filter' => ['status_type' => TaskStatusType::Done->value]])
        );

        $response->assertOk();
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_filters_by_priority(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $wanted = $this->taskIn($list, ['priority' => TaskPriority::Urgent]);
        $this->taskIn($list, ['priority' => TaskPriority::Low]);

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['filter' => ['priority' => TaskPriority::Urgent->value]])
        );

        $response->assertOk();
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_filters_by_assignee(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $wanted = $this->taskIn($list);
        $wanted->assignees()->attach($user);
        $this->taskIn($list);

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['filter' => ['assignee_id' => $user->id]])
        );

        $response->assertOk();
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_filters_by_label(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $list = $this->listIn($space);
        $label = $this->labelIn($space);
        $wanted = $this->taskIn($list);
        $wanted->labels()->attach($label);
        $this->taskIn($list);

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['filter' => ['label_id' => $label->id]])
        );

        $response->assertOk();
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_searches_the_name_and_description(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $byName = $this->taskIn($list, ['name' => 'Wire up OpenClaw', 'description' => 'nothing']);
        $byDescription = $this->taskIn($list, ['name' => 'Chore', 'description' => 'Talk to openclaw']);
        $this->taskIn($list, ['name' => 'Unrelated', 'description' => 'Nope']);

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['filter' => ['search' => 'openclaw']])
        );

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$byName->id, $byDescription->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_index_filters_by_due_date_range(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $wanted = $this->taskIn($list, ['due_at' => '2026-06-15 12:00:00']);
        $this->taskIn($list, ['due_at' => '2026-01-01 12:00:00']);
        $this->taskIn($list, ['due_at' => null]);

        $response = $this->actingAs($user)->getJson($this->tmRoute('tasks.index', [
            'filter' => ['due_after' => '2026-06-01', 'due_before' => '2026-07-01'],
        ]));

        $response->assertOk();
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_filters_by_custom_metadata_field(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $wanted = $this->taskIn($list, ['metadata' => ['sprint' => 'alpha', 'points' => 5]]);
        $this->taskIn($list, ['metadata' => ['sprint' => 'beta']]);
        $this->taskIn($list, ['metadata' => null]);

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['filter' => ['meta' => ['sprint' => 'alpha']]])
        );

        $response->assertOk();
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_returns_only_top_level_tasks_when_the_parent_filter_is_null(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $parent = $this->taskIn($list);
        $this->taskIn($list, ['parent_id' => $parent->id]);

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['filter' => ['parent_id' => 'null']])
        );

        $response->assertOk();
        $this->assertSame([$parent->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_can_include_archived_tasks(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $open = $this->taskIn($list);
        $archived = $this->taskIn($list, ['archived_at' => now()]);

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['filter' => ['archived' => 'with']])
        );

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$open->id, $archived->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_index_can_return_only_archived_tasks(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $this->taskIn($list);
        $archived = $this->taskIn($list, ['archived_at' => now()]);

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['filter' => ['archived' => 'only']])
        );

        $response->assertOk();
        $this->assertSame([$archived->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_sorts_by_due_date_descending(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $earlier = $this->taskIn($list, ['due_at' => '2026-01-01 00:00:00']);
        $later = $this->taskIn($list, ['due_at' => '2026-05-01 00:00:00']);

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['sort' => '-due_at'])
        );

        $response->assertOk();
        $this->assertSame([$later->id, $earlier->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_sorts_by_priority_weight_with_the_most_urgent_first(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $low = $this->taskIn($list, ['priority' => TaskPriority::Low]);
        $urgent = $this->taskIn($list, ['priority' => TaskPriority::Urgent]);
        $normal = $this->taskIn($list, ['priority' => TaskPriority::Normal]);

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('tasks.index', ['sort' => '-priority'])
        );

        $response->assertOk();
        $this->assertSame(
            [$urgent->id, $normal->id, $low->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_index_returns_422_for_an_unknown_sort_column(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson($this->tmRoute('tasks.index', ['sort' => 'password']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    public function test_index_returns_422_for_an_unknown_include(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson($this->tmRoute('tasks.index', ['include' => 'creator.tokens']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('include');
    }

    public function test_index_omits_optional_relations_until_they_are_requested(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $task = $this->taskIn($this->listIn($space));
        $task->labels()->attach($this->labelIn($space));

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.labels');

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.index', ['include' => 'labels']))
            ->assertOk()
            ->assertJsonCount(1, 'data.0.labels');
    }

    public function test_index_returns_422_for_an_out_of_range_page_size(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson($this->tmRoute('tasks.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_list_scoped_index_returns_only_that_list(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $list = $this->listIn($space);
        $wanted = $this->taskIn($list);
        $this->taskIn($this->listIn($space));

        $response = $this->actingAs($user)->getJson($this->tmRoute('lists.tasks.index', $list));

        $response->assertOk();
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    /**
     * Matrix capped the absolute count; Artemis's middleware stack (activity
     * log, workspace and permission checks) adds a fixed overhead per request,
     * so this compares one task against five instead -- the count must not grow
     * with the page.
     */
    public function test_index_loads_relations_without_a_query_per_task(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $list = $this->listIn($space);

        $addTask = function () use ($list, $space, $user): void {
            $task = $this->taskIn($list);
            $task->labels()->attach($this->labelIn($space));
            $task->assignees()->attach($user);
        };

        $countQueries = function (int $expected) use ($user): int {
            $queries = 0;
            DB::listen(function () use (&$queries): void {
                $queries++;
            });

            $this->actingAs($user)
                ->getJson($this->tmRoute('tasks.index', ['include' => 'labels,assignees,status,list']))
                ->assertOk()
                ->assertJsonCount($expected, 'data');

            return $queries;
        };

        $addTask();
        $forOne = $countQueries(1);

        foreach (range(1, 4) as $ignored) {
            $addTask();
        }

        $forFive = $countQueries(5);

        $this->assertSame($forOne, $forFive);
    }
}
