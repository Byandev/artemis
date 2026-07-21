<?php

namespace App\Http\Requests\Workspaces;

use App\Models\Page;
use App\Services\Botcake;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\Rule;
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
            'sms_provider' => 'nullable|in:infotxt,sendgate,sim_gateway',
            'infotxt_token' => 'nullable|string|max:255',
            'infotxt_user_id' => 'nullable|string|max:255',
            'sendgate_api_key' => 'nullable|string|max:255',
            'sendgate_sim_id' => 'nullable|string|max:255',
            'sim_gateway_sim_id' => [
                'nullable',
                'integer',
                'required_if:sms_provider,sim_gateway',
                // Must be a SIM assigned to this workspace.
                Rule::exists('sim_gateway_sims', 'id')
                    ->where('workspace_id', $this->route('workspace')->id),
            ],
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
            'sim_gateway_sim_id.required_if' => 'Please choose which SIM to send from.',
            'sim_gateway_sim_id.exists' => 'That SIM does not belong to this workspace.',
        ];
    }

    /**
     * Validate the parcel-journey flow ID against the live Botcake API. Only
     * enforced while the feature is enabled, and only when a flow ID was
     * actually supplied.
     *
     * Validation asks Botcake for the specific flow's statistics — the same
     * single-flow check used by PageController::validateFlowId — so the save
     * gate agrees with the pre-save check and never rejects a flow that exists
     * upstream (as the lagging local sync mirror could).
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
                (new Botcake((string) $page->id, $token))
                    ->fetchFlowStatistics((string) $this->input('parcel_journey_flow_id'));
            } catch (ConnectionException $e) {
                $validator->errors()->add('parcel_journey_flow_id', 'Could not reach Botcake. Please try again.');
            } catch (\Throwable $e) {
                $message = str_contains($e->getMessage(), 'invalid_page_id')
                    ? "Botcake rejected this page (invalid_page_id). The Botcake access token doesn't match this page's ID — re-copy the access token from Botcake for this exact page."
                    : 'Flow ID not found on this page.';

                $validator->errors()->add('parcel_journey_flow_id', $message);
            }
        });
    }
}
