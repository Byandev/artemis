<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Comment;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\Task;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_comments_are_listed_oldest_first_with_their_author(): void
    {
        $user = User::factory()->create();
        $task = $this->taskOwnedBy($user);

        $first = Comment::factory()->create(['task_id' => $task->id, 'user_id' => $user->id, 'body' => 'First']);
        $second = Comment::factory()->create(['task_id' => $task->id, 'user_id' => $user->id, 'body' => 'Second']);

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.comments.index', $task))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.0.author.id', $user->id)
            ->assertJsonPath('data.0.author.name', $user->name)
            ->assertJsonStructure(['data' => [['id', 'body', 'edited', 'author', 'created_at']]]);
    }

    public function test_a_viewer_can_read_and_post_comments(): void
    {
        $viewer = User::factory()->create();
        $space = $this->spaceWhereUserIs($viewer, SpaceRole::Viewer);
        $task = $this->taskIn($this->listIn($space));

        $this->actingAs($viewer)
            ->postJson($this->tmRoute('tasks.comments.store', $task), ['body' => 'A question from a viewer'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'A question from a viewer')
            ->assertJsonPath('data.edited', false)
            ->assertJsonPath('data.author.id', $viewer->id);

        $this->actingAs($viewer)
            ->getJson($this->tmRoute('tasks.comments.index', $task))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_comment_is_visible_to_every_other_member_of_the_space(): void
    {
        $author = User::factory()->create();
        $space = $this->spaceOwnedBy($author);
        $task = $this->taskIn($this->listIn($space));
        Comment::factory()->create(['task_id' => $task->id, 'user_id' => $author->id, 'body' => 'Shared']);

        $colleague = User::factory()->create();
        $space->members()->attach($colleague, ['role' => SpaceRole::Member->value]);

        $this->actingAs($colleague)
            ->getJson($this->tmRoute('tasks.comments.index', $task))
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Shared');
    }

    public function test_posting_a_comment_changes_nothing_on_the_task(): void
    {
        $user = User::factory()->create();
        $task = $this->taskOwnedBy($user);
        $before = $task->fresh()?->getAttributes();

        $this->actingAs($user)
            ->postJson($this->tmRoute('tasks.comments.store', $task), ['body' => 'Progress note'])
            ->assertCreated();

        $this->assertSame($before, $task->fresh()?->getAttributes());
    }

    public function test_two_people_commenting_at_once_both_keep_their_comment(): void
    {
        $author = User::factory()->create();
        $space = $this->spaceOwnedBy($author);
        $task = $this->taskIn($this->listIn($space));

        $colleague = User::factory()->create();
        $space->members()->attach($colleague, ['role' => SpaceRole::Member->value]);

        $this->actingAs($author)
            ->postJson($this->tmRoute('tasks.comments.store', $task), ['body' => 'Mine'])
            ->assertCreated();
        $this->actingAs($colleague)
            ->postJson($this->tmRoute('tasks.comments.store', $task), ['body' => 'Theirs'])
            ->assertCreated();

        $bodies = $task->comments()->orderBy('id')->pluck('body')->all();

        $this->assertSame(['Mine', 'Theirs'], $bodies);
    }

    public function test_an_author_can_edit_their_own_comment_and_it_is_marked_edited(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'task_id' => $this->taskOwnedBy($user)->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->patchJson($this->tmRoute('comments.update', $comment), ['body' => 'Corrected'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Corrected')
            ->assertJsonPath('data.edited', true);
    }

    public function test_an_author_can_delete_their_own_comment(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'task_id' => $this->taskOwnedBy($user)->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson($this->tmRoute('comments.destroy', $comment))
            ->assertNoContent();

        $this->assertDatabaseMissing('task_comments', ['id' => $comment->id]);
    }

    public function test_a_member_cannot_edit_or_delete_someone_elses_comment(): void
    {
        $author = User::factory()->create();
        $space = $this->spaceOwnedBy($author);
        $task = $this->taskIn($this->listIn($space));
        $comment = Comment::factory()->create(['task_id' => $task->id, 'user_id' => $author->id, 'body' => 'Mine']);

        $colleague = User::factory()->create();
        $space->members()->attach($colleague, ['role' => SpaceRole::Member->value]);

        $this->actingAs($colleague)
            ->patchJson($this->tmRoute('comments.update', $comment), ['body' => 'Hijacked'])
            ->assertForbidden();
        $this->actingAs($colleague)
            ->deleteJson($this->tmRoute('comments.destroy', $comment))
            ->assertForbidden();

        $this->assertSame('Mine', $comment->fresh()?->body);
    }

    /**
     * Compared with debug off, because that is what a client of the deployed app
     * sees. With debug on the framework appends the exception class and trace to
     * any 404 it renders itself, which is a separate leak and is task 8's to fix
     * at the exception handler rather than this route's.
     */
    public function test_an_outsider_gets_the_same_answer_for_an_unreachable_task_and_a_missing_one(): void
    {
        config(['app.debug' => false]);

        $stranger = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceOwnedBy(User::factory()->create())));

        $unreachable = $this->actingAs($stranger)->getJson($this->tmRoute('tasks.comments.index', $task));
        $missing = $this->actingAs($stranger)->getJson($this->tmRoute('tasks.comments.index', $task->id + 1000));

        $unreachable->assertNotFound();
        $missing->assertNotFound();
        $this->assertSame($missing->getContent(), $unreachable->getContent());
    }

    public function test_an_outsider_cannot_post_a_comment(): void
    {
        $stranger = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceOwnedBy(User::factory()->create())));

        $this->actingAs($stranger)
            ->postJson($this->tmRoute('tasks.comments.store', $task), ['body' => 'Intruding'])
            ->assertNotFound();

        $this->assertDatabaseCount('task_comments', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emptyBodyProvider(): array
    {
        return [
            'empty string' => [''],
            'spaces' => ['   '],
            'newlines and tabs' => ["\n\t  \n"],
        ];
    }

    #[DataProvider('emptyBodyProvider')]
    public function test_an_empty_comment_is_refused_and_nothing_is_created(string $body): void
    {
        $user = User::factory()->create();
        $task = $this->taskOwnedBy($user);

        $this->actingAs($user)
            ->postJson($this->tmRoute('tasks.comments.store', $task), ['body' => $body])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body')
            ->assertJsonPath('errors.body.0', 'A comment cannot be empty.');

        $this->assertDatabaseCount('task_comments', 0);
    }

    public function test_line_breaks_survive_and_a_long_comment_is_stored_in_full(): void
    {
        $user = User::factory()->create();
        $task = $this->taskOwnedBy($user);
        $body = "First line\n\nThird line after a blank one\n  indented".str_repeat(' word', 2000);

        $this->actingAs($user)
            ->postJson($this->tmRoute('tasks.comments.store', $task), ['body' => $body])
            ->assertCreated()
            ->assertJsonPath('data.body', $body);

        $this->assertSame($body, Comment::query()->sole()->body);
    }

    public function test_comments_on_an_archived_task_stay_readable(): void
    {
        $user = User::factory()->create();
        $task = $this->taskOwnedBy($user);
        $task->update(['archived_at' => now()]);
        Comment::factory()->create(['task_id' => $task->id, 'user_id' => $user->id, 'body' => 'Still here']);

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.comments.index', $task))
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Still here');
    }

    public function test_an_outsider_gets_the_same_answer_for_an_unreachable_comment_and_a_missing_one(): void
    {
        config(['app.debug' => false]);

        $stranger = User::factory()->create();
        $author = User::factory()->create();
        $comment = Comment::factory()->create([
            'task_id' => $this->taskOwnedBy($author)->id,
            'user_id' => $author->id,
        ]);

        $unreachable = $this->actingAs($stranger)
            ->deleteJson($this->tmRoute('comments.destroy', $comment));
        $missing = $this->actingAs($stranger)
            ->deleteJson($this->tmRoute('comments.destroy', $comment->id + 1000));

        $unreachable->assertNotFound();
        $missing->assertNotFound();
        $this->assertSame($missing->getContent(), $unreachable->getContent());
        $this->assertDatabaseHas('task_comments', ['id' => $comment->id]);
    }

    public function test_a_guest_is_turned_away(): void
    {
        $task = $this->taskOwnedBy(User::factory()->create());

        $this->getJson($this->tmRoute('tasks.comments.index', $task))->assertUnauthorized();
        $this->postJson($this->tmRoute('tasks.comments.store', $task), ['body' => 'Hi'])->assertUnauthorized();
    }

    /**
     * A task in a space the given user owns.
     */
    private function taskOwnedBy(User $user): Task
    {
        return $this->taskIn($this->listIn($this->spaceOwnedBy($user)));
    }
}
