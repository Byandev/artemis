<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Proof of Payment Disk
    |--------------------------------------------------------------------------
    |
    | Where invoice proof-of-payment receipts are stored. S3 in every deployed
    | environment; override to "local" on machines with no AWS credentials.
    | These are private and served through the app via a short-lived signed
    | URL, never a public one.
    |
    | Deliberately NOT named MEDIA_DISK: that env var is media-library's own
    | (`disk_name`), and reusing it would repoint every other collection —
    | product images and the rest — at this bucket too.
    |
    */

    'proof_of_payment_disk' => env('PROOF_OF_PAYMENT_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Checklist Proof Disk
    |--------------------------------------------------------------------------
    |
    | Where the evidence attached when a checklist item is marked complete is
    | stored. Private like the proof-of-payment bucket: the files are served
    | through a signed URL, never a public one.
    |
    */

    'checklist_proof_disk' => env('CHECKLIST_PROOF_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Course Media Disk
    |--------------------------------------------------------------------------
    |
    | Where course thumbnails, videos, and attachments are stored. S3 in every
    | deployed environment; override to "local" on machines with no AWS
    | credentials. The bucket is private, so these are served through the app
    | via a short-lived signed URL, never a public one.
    |
    | Deliberately NOT named MEDIA_DISK, for the same reason as the disk above:
    | that env var is media-library's own (`disk_name`) and setting it would
    | repoint every other collection at this bucket too.
    |
    */

    'course_media_disk' => env('COURSE_MEDIA_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Product Form Media Disk
    |--------------------------------------------------------------------------
    |
    | Where the picture attached to each product-form size/variant is stored.
    | S3 in every deployed environment; override to "local" on machines with
    | no AWS credentials. Served through the app via a short-lived signed URL
    | like the other buckets here, never publicly.
    |
    | Named separately rather than relying on media-library's `disk_name`, for
    | the same reason as the disks above: that env var repoints every other
    | collection too.
    |
    */

    'product_form_media_disk' => env('PRODUCT_FORM_MEDIA_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | RDP Media Disk
    |--------------------------------------------------------------------------
    |
    | Where an RDP's packshot lives — both the render someone uploads and the
    | options the generator produces. Same reasoning as the disks above: pinned
    | explicitly, private, and served through the app with a signed URL.
    |
    */

    'product_research_media_disk' => env('PRODUCT_RESEARCH_MEDIA_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),

            // Failures must surface. With `throw` off, a rejected PutObject
            // returns false and the app happily reports a receipt as attached
            // while the bucket holds nothing.
            'throw' => true,
            'report' => false,

            // No `visibility` key on purpose. Setting it makes Flysystem send
            // an ACL header, which buckets created with ACLs disabled (the
            // modern default, and how amzn-welle-dev is set up) reject outright
            // with AccessControlListNotSupported. Objects are private to the
            // bucket owner and reached through signed URLs.
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
