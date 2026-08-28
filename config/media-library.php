<?php

/*
|--------------------------------------------------------------------------
| Media Library
|--------------------------------------------------------------------------
|
| A PARTIAL override. Spatie's service provider registers its own config with
| mergeConfigFrom, so anything absent here keeps the package default — only the
| keys below are changed. Don't copy the whole vendor file in: that would pin
| every other default at whatever the package shipped when it was copied.
|
*/

return [

    /*
     * The package refuses any file over this, independently of Laravel's own
     * validation, and throws FileIsTooBig from deep inside the FileAdder.
     *
     * The default is 10 MB, which lesson videos are meant to exceed — they are
     * uploaded straight to S3 with a presigned URL precisely so no size ceiling
     * applies. Without raising this, that upload succeeds and then fails when
     * the object is adopted into its collection.
     *
     * Per-collection limits are still enforced where they matter: the course
     * cover image is capped at 10 MB by request validation in
     * CoursesController@rules.
     */
    'max_file_size' => env('MEDIA_MAX_FILE_SIZE', 1024 * 1024 * 1024 * 10), // 10GB

];
