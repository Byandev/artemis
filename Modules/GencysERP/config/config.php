<?php

return [
    'name' => 'GencysERP',

    /*
    |--------------------------------------------------------------------------
    | Sync batch defaults
    |--------------------------------------------------------------------------
    |
    | A batch walks its sync runs through n8n one group at a time, sending the
    | next group only once the previous one has reported back or timed out.
    |
    */
    'batch' => [
        // How long a sent group waits for its n8n callback before its runs are
        // retried, or failed once the attempts run out.
        'timeout_seconds' => (int) env('GENCYS_BATCH_TIMEOUT_SECONDS', 600),

        // Extra attempts a run gets after a timeout or a failed handshake. Each
        // retry goes out on its own, isolated from the group that failed it.
        'max_retries' => (int) env('GENCYS_BATCH_MAX_RETRIES', 2),

        // Runs per n8n request, for the flows whose payload carries an items[]
        // array. The rest are sent one run at a time regardless.
        'group_size' => (int) env('GENCYS_BATCH_GROUP_SIZE', 20),
    ],
];
