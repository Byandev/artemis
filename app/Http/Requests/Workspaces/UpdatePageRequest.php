<?php

namespace App\Http\Requests\Workspaces;

use App\Models\Page;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class UpdatePageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $page = $this->route('page');
        $workspace = $this->route('workspace');

        // Ensure the page belongs to the workspace
        return $page->workspace_id === $workspace->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'facebook_url' => 'nullable|url|max:500',
            'botcake_token' => 'nullable|string|max:255',
            'sms_provider' => 'nullable|in:infotxt,sendgate',
            'infotxt_token' => 'nullable|string|max:255',
            'infotxt_user_id' => 'nullable|string|max:255',
            'sendgate_api_key' => 'nullable|string|max:255',
            'sendgate_sim_id' => 'nullable|string|max:255',
            'pancake_token' => 'nullable|string',
            'parcel_journey_custom_field_id' => 'nullable|integer|min:1',
            'parcel_journey_flow_id' => 'nullable|integer|min:1',
            'parcel_journey_enabled' => 'required|boolean',
            'owner_id' => 'required|integer|exists:users,id',
            'status' => 'required|in:active,inactive',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The page name is required.',
            'facebook_url.url' => 'The Facebook URL must be a valid URL.',
        ];
    }

    /**
     * Validate the parcel-journey flow ID against the locally-synced Botcake
     * flows for this page. Only enforced while the feature is enabled, and only
     * when a flow ID was actually supplied.
     *
     * Custom field IDs have no local mirror, so they are only format-checked
     * (see rules()); there is nothing local to verify their existence against.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('parcel_journey_enabled')) {
                return;
            }

            /** @var Page $page */
            $page = $this->route('page');

            if ($this->filled('parcel_journey_flow_id')
                && ! $this->flowBelongsToPage($page, (int) $this->input('parcel_journey_flow_id'))) {
                $validator->errors()->add(
                    'parcel_journey_flow_id',
                    'The selected flow ID is invalid or does not belong to this page.',
                );
            }
        });
    }

    /**
     * A flow is valid when it is present in the locally-synced Botcake flows
     * for this page and has not been removed upstream.
     */
    protected function flowBelongsToPage(Page $page, int $flowId): bool
    {
        return DB::table('botcake_flows')
            ->where('page_id', $page->id)
            ->where('id', $flowId)
            ->where('is_removed', false)
            ->exists();
    }
}
