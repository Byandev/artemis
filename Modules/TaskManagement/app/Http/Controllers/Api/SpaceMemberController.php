<?php

namespace Modules\TaskManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Http\Requests\StoreSpaceMemberRequest;
use Modules\TaskManagement\Http\Requests\UpdateSpaceMemberRequest;
use Modules\TaskManagement\Http\Resources\SpaceMemberResource;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\Task;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SpaceMemberController extends Controller
{
    /**
     * List everyone a task in this space can be assigned to.
     *
     * The owner is not stored in the members pivot, so they are folded in here
     * to match the set `assignee_ids` validates against. A pivot row for the
     * owner is ignored: ownership outranks whatever role it records.
     */
    public function index(Workspace $workspace, Space $space): AnonymousResourceCollection
    {
        Gate::authorize('view', $space);

        /** @var Collection<int, User> $members */
        $members = $space->members()
            ->orderBy('users.name')
            ->get()
            ->reject(fn (User $member): bool => $member->id === $space->owner_id)
            ->each(fn (User $member) => $member->setAttribute('space_role', $this->pivotRole($member)))
            ->push($space->owner->setAttribute('space_role', SpaceRole::Owner))
            ->sortBy('name')
            ->values();

        return SpaceMemberResource::collection($members);
    }

    /**
     * Add an existing account to the space.
     */
    public function store(Workspace $workspace, StoreSpaceMemberRequest $request, Space $space): JsonResponse
    {
        $user = User::query()->findOrFail($request->integer('user_id'));
        $role = SpaceRole::from($request->string('role')->value());

        $space->members()->attach($user, ['role' => $role->value]);

        return SpaceMemberResource::make($user->setAttribute('space_role', $role))
            ->response()
            ->setStatusCode(HttpStatus::HTTP_CREATED);
    }

    /**
     * Change the role a member holds in the space.
     */
    public function update(Workspace $workspace, UpdateSpaceMemberRequest $request, Space $space, User $user): SpaceMemberResource
    {
        $this->ensureIsAMember($space, $user);

        $role = SpaceRole::from($request->string('role')->value());

        $space->members()->updateExistingPivot($user->id, ['role' => $role->value]);

        return SpaceMemberResource::make($user->setAttribute('space_role', $role));
    }

    /**
     * Remove a member from the space.
     */
    public function destroy(Workspace $workspace, Space $space, User $user): Response
    {
        Gate::authorize('manageMembers', $space);

        $this->ensureIsAMember($space, $user);

        DB::transaction(function () use ($space, $user): void {
            // Assignments would otherwise outlive the membership and fail the
            // `assignee_ids` check the next time that task is updated. Only the
            // assignments inside this space go -- the user's tasks in every
            // other space keep them.
            DB::table('task_assignees')
                ->where('user_id', $user->id)
                ->whereIn('task_id', Task::query()
                    ->whereIn('task_list_id', $space->lists()->select('id'))
                    ->select('id'))
                ->delete();

            $space->members()->detach($user);
        });

        return response()->noContent();
    }

    /**
     * Refuse to re-role or remove anyone who is not a member of the space.
     *
     * The owner is deliberately included: ownership is transferred with the
     * space itself, so it cannot be edited away through the members endpoint.
     */
    private function ensureIsAMember(Space $space, User $user): void
    {
        if ($user->id === $space->owner_id) {
            throw ValidationException::withMessages([
                'user' => __('The owner of a space cannot be re-roled or removed.'),
            ]);
        }

        if (! $space->members()->whereKey($user->id)->exists()) {
            abort(HttpStatus::HTTP_NOT_FOUND);
        }
    }

    /**
     * Read the role a loaded member holds from their pivot row.
     */
    private function pivotRole(User $member): SpaceRole
    {
        return SpaceRole::from($member->getAttribute('pivot')->role);
    }
}
