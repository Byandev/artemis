<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOptimizationRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isMemberOf($this->route('workspace'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'target' => 'sometimes|required|in:campaign,ad_set',
            'action' => 'sometimes|required|in:increase_budget_fixed,decrease_budget_fixed,increase_budget_percentage,decrease_budget_percentage',
            'action_value' => 'nullable|numeric|min:0',
            'conditions' => 'sometimes|required|array',
            'conditions.*.metric' => 'required|in:spend,impressions,clicks,sales,roas',
            'conditions.*.operator' => 'required|in:greater_than,less_than,equal,greater_than_or_equal,less_than_or_equal',
            'conditions.*.value' => 'required|numeric',
            'status' => 'nullable|in:active,paused',
        ];
    }
}
