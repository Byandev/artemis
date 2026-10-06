<?php

namespace Modules\Creatives\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Creatives\Models\Creative;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                'revision',
                'approved',
            ])],
            'feedback' => ['nullable', 'string', 'max:2000'],
            // A voice message recorded in the browser. Chrome records audio-only
            // WebM that file sniffing reports as video/webm, and Safari's MP4
            // can read as video/mp4, so those two are let through as well.
            // 20 MB is far beyond the 5-minute cap the recorder enforces.
            'voice' => ['nullable', 'file', 'mimetypes:audio/*,video/webm,video/mp4', 'max:20480'],
            'voice_duration_seconds' => ['nullable', 'integer', 'min:0', 'max:600'],
            // The second of the video the review points at. Only video
            // creatives have a timeline to point into.
            'timestamp_seconds' => [
                'nullable', 'numeric', 'min:0', 'max:86400',
                Rule::prohibitedIf(fn () => $this->creative()?->format !== 'video'),
            ],
            // The area of the image the review points at, as fractions of the
            // image. Only an image uploaded to Artemis can be marked up.
            'region' => [
                'nullable', 'array:x,y,w,h',
                Rule::prohibitedIf(fn () => ! $this->hasUploadedImage()),
            ],
            'region.x' => ['required_with:region', 'numeric', 'between:0,1'],
            'region.y' => ['required_with:region', 'numeric', 'between:0,1'],
            'region.w' => ['required_with:region', 'numeric', 'between:0,1'],
            'region.h' => ['required_with:region', 'numeric', 'between:0,1'],
        ];
    }

    /** A region must sit inside the image. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $region = $this->input('region');

            if (! is_array($region) || $validator->errors()->hasAny(['region', 'region.x', 'region.y', 'region.w', 'region.h'])) {
                return;
            }

            // A hair of slack for floating point on a box dragged to the edge.
            if ((float) ($region['x'] ?? 0) + (float) ($region['w'] ?? 0) > 1.0001
                || (float) ($region['y'] ?? 0) + (float) ($region['h'] ?? 0) > 1.0001) {
                $validator->errors()->add('region', 'The marked area must sit inside the image.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'timestamp_seconds.prohibited' => 'Only video creatives can be reviewed at a timestamp.',
            'region.prohibited' => 'Only an uploaded image can be marked up.',
        ];
    }

    private function hasUploadedImage(): bool
    {
        return (bool) str_starts_with((string) $this->creative()?->mediaFile()?->mime_type, 'image/');
    }

    private function creative(): ?Creative
    {
        $creative = $this->route('creative');

        return $creative instanceof Creative ? $creative : null;
    }
}
