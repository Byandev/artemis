<?php

namespace Modules\TaskManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\TaskManagement\Http\Requests\StoreCommentRequest;
use Modules\TaskManagement\Http\Requests\UpdateCommentRequest;
use Modules\TaskManagement\Http\Resources\CommentResource;
use Modules\TaskManagement\Models\Comment;
use Modules\TaskManagement\Models\Task;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class CommentController extends Controller
{
    /**
     * List the comments on a task, oldest first.
     *
     * Not paginated, for the reason api.md gives for the other index endpoints:
     * the set is small and the page reads all of it.
     */
    public function index(Workspace $workspace, Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return CommentResource::collection(
            $task->comments()->with('author')->orderBy('id')->get()
        );
    }

    /**
     * Post a comment on a task.
     *
     * The task itself is untouched -- a comment is a new row, never an edit of
     * the description, which is the whole point of the feature.
     */
    public function store(Workspace $workspace, StoreCommentRequest $request, Task $task): JsonResponse
    {
        $comment = $task->comments()->make($request->safe()->only(['body']));
        $comment->user_id = $request->user()->id;
        $comment->save();

        return CommentResource::make($comment->load('author'))
            ->response()
            ->setStatusCode(HttpStatus::HTTP_CREATED);
    }

    /**
     * Edit your own comment, marking it as edited.
     */
    public function update(Workspace $workspace, UpdateCommentRequest $request, Comment $comment): CommentResource
    {
        $comment->fill($request->safe()->only(['body']));
        $comment->edited_at = now();
        $comment->save();

        return CommentResource::make($comment->load('author'));
    }

    /**
     * Delete your own comment.
     */
    public function destroy(Workspace $workspace, Comment $comment): Response
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }
}
