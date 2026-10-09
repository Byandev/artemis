<?php

namespace Modules\TaskManagement\Http\Requests;

use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Space;

class StoreSpaceMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('manageMembers', $this->route('space'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Space $space */
        $space = $this->route('space');

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                $this->inWorkspaceRule($space),
                $this->notAlreadyInSpaceRule($space),
            ],
            'role' => ['required', Rule::enum(SpaceRole::class)->except(SpaceRole::Owner)],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => __('That account no longer exists.'),
        ];
    }

    /**
     * Reject anyone who is not a member of the space's Artemis workspace.
     */
    private function inWorkspaceRule(Space $space): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($space): void {
            if (! $space->workspaceUsers()->whereKey($value)->exists()) {
                $fail(__('That user is not a member of this workspace.'));
            }
        };
    }

    /**
     * Reject someone who already belongs to the space, as owner or as member.
     */
    private function notAlreadyInSpaceRule(Space $space): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($space): void {
            $isOwner = $space->owner_id === (int) $value;
            $isMember = $space->members()->whereKey($value)->exists();

            if ($isOwner || $isMember) {
                $fail(__('That user is already in this space.'));
            }
        };
    }
}
