<?php

namespace Modules\Creatives\Http\Requests\Concerns;

use Illuminate\Validation\Validator;
use Symfony\Component\Mime\MimeTypes;

/**
 * An image creative takes an image file and a video creative a video. The form
 * enforces this too; this stops a request that skips it.
 */
trait ChecksMediaMatchesFormat
{
    protected function checkMediaMatchesFormat(Validator $validator, ?string $format): void
    {
        if (! in_array($format, ['image', 'video'], true)) {
            return;
        }

        $noun = $format === 'image' ? 'an image' : 'a video';

        if ($this->hasFile('media_file')) {
            if (! str_starts_with((string) $this->file('media_file')->getMimeType(), "{$format}/")) {
                $validator->errors()->add('media_file', "The file must be {$noun}.");
            }

            return;
        }

        // The bytes are already in the bucket; judge them by the extension
        // the key was minted with (CreativeMediaStorage::presignUpload).
        $key = (string) $this->input('media_key');
        if ($key === '') {
            return;
        }

        $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $matches = collect(MimeTypes::getDefault()->getMimeTypes($extension))
            ->contains(fn (string $mime) => str_starts_with($mime, "{$format}/"));

        if (! $matches) {
            $validator->errors()->add('media_key', "The file must be {$noun}.");
        }
    }
}
