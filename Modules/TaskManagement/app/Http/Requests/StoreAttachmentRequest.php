<?php

namespace Modules\TaskManagement\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

class StoreAttachmentRequest extends FormRequest
{
    /**
     * The file types a task will accept.
     *
     * An allowlist rather than a blocklist, and checked twice below: once
     * against the name the client sent and once against the type guessed from
     * the bytes, because either check alone passes a file the other would
     * refuse.
     *
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'svg',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'csv', 'txt', 'md', 'json', 'zip',
        'mp3', 'mp4', 'mov', 'webm',
    ];

    /**
     * The largest file a task will accept, in kilobytes.
     *
     * Kept at or below `media-library.max_file_size`, which throws rather than
     * answering 422, so validation is what a client actually meets.
     */
    public const MAX_KILOBYTES = 25 * 1024;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Attaching is gated on being able to edit the task, not merely to see it:
     * a document is part of the work, so it takes a member. That is the one
     * difference from commenting, which a viewer may do.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('task'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(self::ALLOWED_EXTENSIONS)
                    ->extensions(self::ALLOWED_EXTENSIONS)
                    ->max(self::MAX_KILOBYTES),
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a file to attach.',
            'file.max' => 'A file may be at most 25 MB.',
            'file.mimes' => 'That file type cannot be attached.',
            'file.extensions' => 'That file type cannot be attached.',
        ];
    }
}
