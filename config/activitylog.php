<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activity model
    |--------------------------------------------------------------------------
    |
    | When using a custom Activity model you can specify it here so the package
    | uses your model instead of the default one shipped with the package.
    |
    */
    'activity_model' => App\Models\Activity::class,

    /*
    |--------------------------------------------------------------------------
    | Default log retention (days)
    |--------------------------------------------------------------------------
    |
    | Number of days to keep activity records. The scheduled `activitylog:clean`
    | command will remove older entries. Default to 365 for compliance.
    |
    */
    'retention_in_days' => env('ACTIVITYLOG_RETENTION_DAYS', 365),

    // Minimal other defaults - package will still provide defaults when published
];
