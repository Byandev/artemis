<?php

namespace Modules\TaskManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\TaskManagement\Http\Requests\StoreAttachmentRequest;
use Modules\TaskManagement\Http\Resources\AttachmentResource;
use Modules\TaskManagement\Models\Attachment;
use Modules\TaskManagement\Models\Task;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    /**
     * List the files attached to a task, oldest first.
     *
     * Not paginated, for the reason api.md gives for the other index endpoints:
     * the set is small and the page reads all of it.
     */
    public function index(Workspace $workspace, Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return AttachmentResource::collection(
            $task->attachments()->with('uploader')->get()
        );
    }

    /**
     * Attach a file to a task.
     *
     * The task row itself is untouched: a file is a new record on the disk the
     * media library is configured with, not an edit of the task.
     */
    public function store(Workspace $workspace, StoreAttachmentRequest $request, Task $task): JsonResponse
    {
        /** @var Attachment $attachment */
        $attachment = $task
            ->addMediaFromRequest('file')
            ->withAttributes(['uploaded_by' => $request->user()->id])
            ->toMediaCollection(Task::ATTACHMENTS);

        return AttachmentResource::make($attachment->load('uploader'))
            ->response()
            ->setStatusCode(HttpStatus::HTTP_CREATED);
    }

    /**
     * Send the file to someone who may read the task.
     *
     * Streamed through this route rather than redirected to a signed link into
     * the bucket. The signed link is cheaper -- the bytes never reach a worker --
     * but it outlives the check that produced it, and on a disk other than S3 it
     * carries no say over how the browser treats what it serves. Streaming keeps
     * every fetch behind the policy and answers with an attachment disposition on
     * any disk, so nothing an uploader chose is ever rendered as a page of its
     * own. Worth revisiting if egress rather than access becomes the cost that
     * hurts; files are capped at 25MB, which is what makes this affordable.
     */
    public function download(Workspace $workspace, Request $request, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return $attachment->toResponse($request);
    }

    /**
     * Show the file in the browser, or hand it over when showing it is not safe.
     *
     * Inline means the file renders on this application's own origin, so what
     * may render is decided by `Attachment::isPreviewable()` -- from the content
     * type the server detected at upload, never from the file name. Anything
     * else falls back to the same attachment disposition `download` uses, so a
     * `.svg` full of `<script>` or an HTML page saved as `photo.jpg` is saved
     * rather than executed. That fallback is also why this never answers 404:
     * the link is safe to follow for any file, and the browser does whatever it
     * can with what it is given.
     *
     * `nosniff` stops a browser second-guessing the content type it is sent and
     * reinterpreting one of these files as a document.
     */
    public function preview(Workspace $workspace, Request $request, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        $response = $attachment->isPreviewable()
            ? $attachment->toInlineResponse($request)
            : $attachment->toResponse($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /**
     * Delete a file, taking it off the disk with the row.
     */
    public function destroy(Workspace $workspace, Attachment $attachment): Response
    {
        Gate::authorize('delete', $attachment);

        $attachment->delete();

        return response()->noContent();
    }
}
