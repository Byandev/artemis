<?php

namespace Modules\TaskManagement\Http\Requests;

use App\Models\Workspace;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Support\TicketCodes;

class StoreSpaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('create', Space::class);
    }

    /**
     * Fill in the code the name derives to when the user did not choose one,
     * moving to the first free variant (ART2, ART3) when it is taken. A typed
     * code is only upper-cased, and must then be free.
     */
    protected function prepareForValidation(): void
    {
        $code = $this->string('code')->trim()->toString();
        $name = $this->string('name')->trim()->toString();

        if ($code === '' && $name !== '') {
            $code = TicketCodes::firstFreeFor($this->workspace()->id, $name);
        }

        if ($code !== '') {
            $this->merge(['code' => mb_strtoupper($code)]);
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'A space code is letters and digits, starting with a letter.',
        ];
    }

    private function workspace(): Workspace
    {
        return $this->route('workspace');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:'.TicketCodes::MAX_LENGTH, 'regex:'.TicketCodes::PATTERN,
                TicketCodes::availableRule($this->workspace()->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:32'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
