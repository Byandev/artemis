<?php

namespace Modules\Courses\Http\Controllers\Concerns;

use Modules\Courses\Models\Course;

/**
 * The course shape both the pages and the courses API send to the browser.
 */
trait PresentsCourses
{
    /**
     * The raw media rows carry disk paths and custom properties the page has
     * no use for, so they don't get shipped to the browser. No URL is
     * serialized either — the page links to the media route, which signs one
     * on demand.
     *
     * @return array<string, mixed>
     */
    protected function present(Course $course): array
    {
        $cover = $course->coverImage();

        return [
            'id' => $course->id,
            'name' => $course->name,
            'description' => $course->description,
            'category' => $course->category,
            'status' => $course->status,
            'modules_count' => $course->modules_count,
            'created_at' => $course->created_at?->toIso8601String(),
            'updated_at' => $course->updated_at?->toIso8601String(),
            'cover_image' => $cover ? [
                'id' => $cover->id,
                'file_name' => $cover->file_name,
                'mime_type' => $cover->mime_type,
                'size' => $cover->size,
            ] : null,
        ];
    }
}
