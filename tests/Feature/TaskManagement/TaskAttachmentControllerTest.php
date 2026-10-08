<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Attachment;
use Modules\TaskManagement\Models\Task;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class TaskAttachmentControllerTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    /**
     * The smallest byte sequence the server's content detection reads as a PDF.
     *
     * Previewing is decided from the detected type, so a file of placeholder
     * text named `.pdf` would not stand in for one.
     */
    private const PDF_BYTES = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

    public function test_attachments_are_listed_oldest_first_with_their_uploader(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $task = $this->taskOwnedBy($user);

        $first = $this->attach($task, $user, 'brief.pdf');
        $second = $this->attach($task, $user, 'mockup.png');

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.attachments.index', $task))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.0.file_name', 'brief.pdf')
            ->assertJsonPath('data.0.uploader.id', $user->id)
            ->assertJsonPath('data.0.uploader.name', $user->name)
            ->assertJsonStructure(['data' => [[
                'id', 'task_id', 'name', 'file_name', 'mime_type', 'size',
                'download_url', 'preview_url', 'previewable', 'uploader', 'created_at',
            ]]]);
    }

    public function test_a_member_attaching_a_file_gets_201_and_the_file_lands_on_the_media_disk(): void
    {
        Storage::fake('s3');
        $member = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceWhereUserIs($member, SpaceRole::Member)));

        $this->actingAs($member)
            ->post(
                $this->tmRoute('tasks.attachments.store', $task),
                ['file' => UploadedFile::fake()->createWithContent('brief.pdf', 'the brief')],
                ['Accept' => 'application/json'],
            )
            ->assertCreated()
            ->assertJsonPath('data.task_id', $task->id)
            ->assertJsonPath('data.file_name', 'brief.pdf')
            ->assertJsonPath('data.size', 9)
            ->assertJsonPath('data.uploader.id', $member->id);

        $attachment = Attachment::query()->sole();

        $this->assertSame(Task::ATTACHMENTS, $attachment->collection_name);
        $this->assertSame('s3', $attachment->disk);
        $this->assertSame($member->id, $attachment->uploaded_by);
        $this->assertSame($task->id, (int) $attachment->model_id);

        Storage::disk('s3')->assertExists($attachment->getPathRelativeToRoot());
    }

    public function test_attaching_a_file_changes_nothing_on_the_task(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $task = $this->taskOwnedBy($user);
        $before = $task->fresh()?->getAttributes();

        $this->actingAs($user)
            ->post(
                $this->tmRoute('tasks.attachments.store', $task),
                ['file' => UploadedFile::fake()->createWithContent('brief.pdf', 'the brief')],
                ['Accept' => 'application/json'],
            )
            ->assertCreated();

        $this->assertSame($before, $task->fresh()?->getAttributes());
    }

    /**
     * A viewer may read the discussion but not add to the work, which is the one
     * place attachments and comments part company -- a viewer can comment.
     */
    public function test_a_viewer_gets_403_when_attaching_a_file(): void
    {
        Storage::fake('s3');
        $viewer = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceWhereUserIs($viewer, SpaceRole::Viewer)));

        $this->actingAs($viewer)
            ->post(
                $this->tmRoute('tasks.attachments.store', $task),
                ['file' => UploadedFile::fake()->createWithContent('brief.pdf', 'the brief')],
                ['Accept' => 'application/json'],
            )
            ->assertForbidden();

        $this->assertDatabaseCount('media', 0);
    }

    public function test_a_viewer_can_download_a_file_attached_by_someone_else(): void
    {
        Storage::fake('s3');
        $viewer = User::factory()->create();
        $space = $this->spaceWhereUserIs($viewer, SpaceRole::Viewer);
        $task = $this->taskIn($this->listIn($space));
        $attachment = $this->attach($task, $space->owner, 'brief.pdf', 'the brief');

        $response = $this->actingAs($viewer)
            ->get($this->tmRoute('attachments.download', $attachment));

        $response->assertDownload('brief.pdf');
        $this->assertSame('the brief', $response->streamedContent());
    }

    public function test_the_uploader_can_delete_their_own_file_and_it_leaves_the_disk(): void
    {
        Storage::fake('s3');
        $member = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceWhereUserIs($member, SpaceRole::Member)));
        $attachment = $this->attach($task, $member);
        $path = $attachment->getPathRelativeToRoot();

        $this->actingAs($member)
            ->deleteJson($this->tmRoute('attachments.destroy', $attachment))
            ->assertNoContent();

        $this->assertDatabaseMissing('media', ['id' => $attachment->id]);
        Storage::disk('s3')->assertMissing($path);
    }

    public function test_a_member_gets_403_deleting_a_file_someone_else_attached(): void
    {
        Storage::fake('s3');
        $space = $this->spaceOwnedBy(User::factory()->create());
        $task = $this->taskIn($this->listIn($space));
        $attachment = $this->attach($task, $space->owner);

        $colleague = User::factory()->create();
        $space->members()->attach($colleague, ['role' => SpaceRole::Member->value]);

        $this->actingAs($colleague)
            ->deleteJson($this->tmRoute('attachments.destroy', $attachment))
            ->assertForbidden()
            ->assertJsonPath('message', 'Only the person who attached this file, or a space admin, can delete it.');

        $this->assertDatabaseHas('media', ['id' => $attachment->id]);
        Storage::disk('s3')->assertExists($attachment->getPathRelativeToRoot());
    }

    /**
     * A file is a shared artefact of the task, so it must stay removable after
     * the person who attached it has gone.
     */
    public function test_a_space_admin_can_delete_a_file_someone_else_attached(): void
    {
        Storage::fake('s3');
        $space = $this->spaceOwnedBy(User::factory()->create());
        $task = $this->taskIn($this->listIn($space));
        $attachment = $this->attach($task, $space->owner);

        $admin = User::factory()->create();
        $space->members()->attach($admin, ['role' => SpaceRole::Admin->value]);

        $this->actingAs($admin)
            ->deleteJson($this->tmRoute('attachments.destroy', $attachment))
            ->assertNoContent();

        $this->assertDatabaseMissing('media', ['id' => $attachment->id]);
    }

    /**
     * `tasks.parent_id` cascades in the database, so the subtask row goes without
     * an Eloquent event and nothing would clear its file on its own.
     */
    public function test_deleting_a_task_takes_the_files_of_its_subtasks_off_the_disk(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $list = $this->listIn($space);
        $parent = $this->taskIn($list);
        $child = $this->taskIn($list, ['parent_id' => $parent->id]);
        $grandchild = $this->taskIn($list, ['parent_id' => $child->id]);

        $paths = collect([$parent, $child, $grandchild])
            ->map(fn (Task $task): string => $this->attach($task, $user)->getPathRelativeToRoot());

        $this->actingAs($user)
            ->deleteJson($this->tmRoute('tasks.destroy', $parent))
            ->assertNoContent();

        $this->assertDatabaseCount('media', 0);
        $paths->each(fn (string $path) => Storage::disk('s3')->assertMissing($path));
    }

    /**
     * Space deletion removes its tasks with one mass delete, which fires no
     * Eloquent event either.
     */
    public function test_deleting_a_space_takes_the_files_of_its_tasks_off_the_disk(): void
    {
        Storage::fake('s3');
        $owner = User::factory()->create();
        $space = $this->spaceOwnedBy($owner);
        $task = $this->taskIn($this->listIn($space));
        $path = $this->attach($task, $owner)->getPathRelativeToRoot();

        $this->actingAs($owner)
            ->deleteJson($this->tmRoute('spaces.destroy', $space))
            ->assertNoContent();

        $this->assertDatabaseCount('media', 0);
        Storage::disk('s3')->assertMissing($path);
    }

    /**
     * Compared with debug off, because that is what a client of the deployed app
     * sees -- the same comparison CommentControllerTest makes, for the same
     * reason: nothing may confirm another tenant's record exists.
     */
    public function test_an_outsider_gets_the_same_answer_for_an_unreachable_task_and_a_missing_one(): void
    {
        config(['app.debug' => false]);

        $stranger = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceOwnedBy(User::factory()->create())));

        $unreachable = $this->actingAs($stranger)->getJson($this->tmRoute('tasks.attachments.index', $task));
        $missing = $this->actingAs($stranger)->getJson($this->tmRoute('tasks.attachments.index', $task->id + 1000));

        $unreachable->assertNotFound();
        $missing->assertNotFound();
        $this->assertSame($missing->getContent(), $unreachable->getContent());
    }

    public function test_an_outsider_gets_the_same_answer_for_an_unreachable_file_and_a_missing_one(): void
    {
        config(['app.debug' => false]);
        Storage::fake('s3');

        $owner = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceOwnedBy($owner)));
        $attachment = $this->attach($task, $owner);

        $stranger = User::factory()->create();

        $unreachable = $this->actingAs($stranger)->getJson($this->tmRoute('attachments.download', $attachment));
        $missing = $this->actingAs($stranger)->getJson($this->tmRoute('attachments.download', $attachment->id + 1000));

        $unreachable->assertNotFound();
        $missing->assertNotFound();
        $this->assertSame($missing->getContent(), $unreachable->getContent());
        Storage::disk('s3')->assertExists($attachment->getPathRelativeToRoot());
    }

    public function test_an_outsider_cannot_attach_a_file(): void
    {
        Storage::fake('s3');
        $stranger = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceOwnedBy(User::factory()->create())));

        $this->actingAs($stranger)
            ->post(
                $this->tmRoute('tasks.attachments.store', $task),
                ['file' => UploadedFile::fake()->createWithContent('brief.pdf', 'intruding')],
                ['Accept' => 'application/json'],
            )
            ->assertNotFound();

        $this->assertDatabaseCount('media', 0);
    }

    public function test_an_image_is_shown_in_the_browser_rather_than_downloaded(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $attachment = $this->attachFile(
            $this->taskOwnedBy($user),
            $user,
            UploadedFile::fake()->image('mockup.png', 20, 20),
        );

        $this->actingAs($user)
            ->get($this->tmRoute('attachments.preview', $attachment))
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('content-disposition', 'inline; filename="mockup.png"')
            ->assertHeader('x-content-type-options', 'nosniff');
    }

    public function test_a_pdf_is_shown_in_the_browser_rather_than_downloaded(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $attachment = $this->attach($this->taskOwnedBy($user), $user, 'contract.pdf', self::PDF_BYTES);

        $this->actingAs($user)
            ->get($this->tmRoute('attachments.preview', $attachment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="contract.pdf"');
    }

    /**
     * Rendering inline happens on this application's own origin, so a document
     * that can carry script must never get an inline disposition.
     *
     * The SVG is the reachable case: `.svg` is in the upload allowlist, so a
     * space member can store exactly this. The HTML page named `.jpg` cannot
     * currently be uploaded -- validation reads the content type too and refuses
     * it -- and is here because this route must not depend on that for safety.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function unrenderableFileProvider(): array
    {
        return [
            'svg carrying a script' => [
                'logo.svg',
                '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
                'image/svg+xml',
            ],
            'html page named as a jpeg' => [
                'photo.jpg',
                '<html><body><script>alert(1)</script></body></html>',
                'text/html',
            ],
        ];
    }

    #[DataProvider('unrenderableFileProvider')]
    public function test_a_file_that_could_carry_script_is_handed_over_instead_of_rendered(
        string $fileName,
        string $contents,
        string $detectedType,
    ): void {
        Storage::fake('s3');
        $user = User::factory()->create();
        $attachment = $this->attach($this->taskOwnedBy($user), $user, $fileName, $contents);

        $this->assertSame($detectedType, $attachment->mime_type);

        $this->actingAs($user)
            ->get($this->tmRoute('attachments.preview', $attachment))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="'.$fileName.'"')
            ->assertHeader('x-content-type-options', 'nosniff');
    }

    public function test_the_payload_says_which_files_the_browser_will_render(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $task = $this->taskOwnedBy($user);
        $this->attachFile($task, $user, UploadedFile::fake()->image('mockup.png', 20, 20));
        $this->attach($task, $user, 'handover.zip', "PK\x03\x04nothing useful");

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.attachments.index', $task))
            ->assertOk()
            ->assertJsonPath('data.0.previewable', true)
            ->assertJsonPath('data.1.previewable', false);
    }

    public function test_an_outsider_cannot_preview_a_file(): void
    {
        Storage::fake('s3');
        $owner = User::factory()->create();
        $attachment = $this->attachFile(
            $this->taskOwnedBy($owner),
            $owner,
            UploadedFile::fake()->image('mockup.png', 20, 20),
        );

        $this->actingAs(User::factory()->create())
            ->getJson($this->tmRoute('attachments.preview', $attachment))
            ->assertNotFound();
    }

    public function test_a_guest_is_turned_away(): void
    {
        $task = $this->taskOwnedBy(User::factory()->create());

        $this->getJson($this->tmRoute('tasks.attachments.index', $task))->assertUnauthorized();
        $this->postJson($this->tmRoute('tasks.attachments.store', $task))->assertUnauthorized();
    }

    public function test_a_request_with_no_file_is_refused_with_422(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $task = $this->taskOwnedBy($user);

        $this->actingAs($user)
            ->postJson($this->tmRoute('tasks.attachments.store', $task))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file')
            ->assertJsonPath('errors.file.0', 'Choose a file to attach.');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_an_unsupported_file_type_is_refused_with_422_and_nothing_is_stored(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $task = $this->taskOwnedBy($user);

        $this->actingAs($user)
            ->post(
                $this->tmRoute('tasks.attachments.store', $task),
                ['file' => UploadedFile::fake()->create('installer.exe', 12)],
                ['Accept' => 'application/json'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file')
            ->assertJsonPath('errors.file.0', 'That file type cannot be attached.');

        $this->assertDatabaseCount('media', 0);
        Storage::disk('s3')->assertDirectoryEmpty('');
    }

    public function test_a_file_over_25_mb_is_refused_with_422(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $task = $this->taskOwnedBy($user);

        $this->actingAs($user)
            ->post(
                $this->tmRoute('tasks.attachments.store', $task),
                ['file' => UploadedFile::fake()->create('huge.pdf', 25 * 1024 + 1)],
                ['Accept' => 'application/json'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file')
            ->assertJsonPath('errors.file.0', 'A file may be at most 25 MB.');

        $this->assertDatabaseCount('media', 0);
    }

    /**
     * The kinds of file the allowlist exists to let through. Each is one an
     * agency actually attaches to a task, and each has a way to be rejected by
     * accident -- an extension with no mime of its own, or one Laravel maps to a
     * different extension than the name suggests.
     *
     * @return array<string, array{string}>
     */
    public static function acceptedFileNameProvider(): array
    {
        return [
            'pdf' => ['contract.pdf'],
            'word' => ['scope.docx'],
            'excel' => ['budget.xlsx'],
            'csv' => ['export.csv'],
            'markdown' => ['notes.md'],
            'json' => ['payload.json'],
            'zip' => ['handover.zip'],
            'svg' => ['logo.svg'],
            'jpeg' => ['photo.jpg'],
            'video' => ['walkthrough.mp4'],
        ];
    }

    #[DataProvider('acceptedFileNameProvider')]
    public function test_an_expected_file_type_is_accepted(string $fileName): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $task = $this->taskOwnedBy($user);

        $this->actingAs($user)
            ->post(
                $this->tmRoute('tasks.attachments.store', $task),
                ['file' => UploadedFile::fake()->createWithContent($fileName, 'contents')],
                ['Accept' => 'application/json'],
            )
            ->assertCreated()
            ->assertJsonPath('data.file_name', $fileName);
    }

    /**
     * A task in a space the given user owns.
     */
    private function taskOwnedBy(User $user): Task
    {
        return $this->taskIn($this->listIn($this->spaceOwnedBy($user)));
    }

    /**
     * Attach a file to a task the way the endpoint does, for the tests that need
     * one to already be there.
     */
    private function attach(Task $task, User $uploader, string $fileName = 'brief.pdf', string $contents = 'the brief'): Attachment
    {
        return $this->attachFile($task, $uploader, UploadedFile::fake()->createWithContent($fileName, $contents));
    }

    /**
     * Attach an upload built by the caller, for the tests that need real bytes
     * of a particular kind rather than placeholder text.
     */
    private function attachFile(Task $task, User $uploader, UploadedFile $file): Attachment
    {
        /** @var Attachment $attachment */
        $attachment = $task
            ->addMedia($file)
            ->withAttributes(['uploaded_by' => $uploader->id])
            ->toMediaCollection(Task::ATTACHMENTS);

        return $attachment;
    }
}
