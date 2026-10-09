<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class TasksPageTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_tasks_page_redirects_guests_to_login(): void
    {
        $this->get($this->tmPage('index'))->assertRedirect(route('login'));
    }

    public function test_tasks_page_renders_for_an_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get($this->tmPage('index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('workspaces/tasks/index'));
    }

    public function test_task_detail_page_passes_only_the_identifier(): void
    {
        $user = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceOwnedBy($user)));

        $this->actingAs($user)
            ->get($this->tmPage('show', $task))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/tasks/show')
                ->where('taskId', $task->id)
                ->missing('task'));
    }

    public function test_task_detail_page_returns_404_for_a_task_outside_the_users_spaces(): void
    {
        $stranger = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceOwnedBy(User::factory()->create())));

        $this->actingAs($stranger)
            ->get($this->tmPage('show', $task))
            ->assertNotFound();
    }

    public function test_task_detail_page_redirects_guests_to_login(): void
    {
        $task = $this->taskIn($this->listIn($this->spaceOwnedBy(User::factory()->create())));

        $this->get($this->tmPage('show', $task))->assertRedirect(route('login'));
    }
}
