<?php

namespace App\Http\Requests\Workspaces;

use App\Models\Page;
use App\Services\Botcake;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
     * Validate the parcel-journey flow ID against the live Botcake API. Only
     * enforced while the feature is enabled, and only when a flow ID was
     * actually supplied.
     *
     * The botcake_flows table is deliberately not used here: it is a sync
     * mirror that lags (and can partially fail), so checking against it
     * rejects flows that exist perfectly well upstream. Asking Botcake keeps
     * this in agreement with the pre-save check in PageController.
     *
     * Custom field IDs are only format-checked (see rules()).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('parcel_journey_enabled') || ! $this->filled('parcel_journey_flow_id')) {
                return;
            }

            /** @var Page $page */
            $page = $this->route('page');

            // Check against the token being saved, not the stored one, so a
            // token and flow ID changed together validate as a pair.
            $token = $this->input('botcake_token') ?: $page->botcake_token;

            if (blank($token)) {
                $validator->errors()->add(
                    'parcel_journey_flow_id',
                    'Enter a Botcake token before setting a flow ID.',
                );

                return;
            }

            try {
                $flows = (new Botcake((string) $page->id, $token))->fetchFlows();
            } catch (\Throwable $e) {
                $validator->errors()->add('parcel_journey_flow_id', Botcake::describeError($e));

                return;
            }

            if (! $this->flowExists($flows, (int) $this->input('parcel_journey_flow_id'))) {
                $validator->errors()->add(
                    'parcel_journey_flow_id',
                    'Flow ID not found on this page.',
                );
            }
        });
    }

    /**
     * A flow is valid when Botcake still lists it for this page and has not
     * marked it removed.
     *
     * @param  array<int, array<string, mixed>>  $flows
     */
    protected function flowExists(array $flows, int $flowId): bool
    {
        return collect($flows)->contains(
            fn ($flow) => (int) ($flow['id'] ?? 0) === $flowId
                && ! ($flow['is_removed'] ?? false),
        );
    }
}
