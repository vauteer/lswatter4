<?php

return [
    'directory' => env('BACKUP_DIRECTORY', storage_path('backups')),
    'mysql_binary' => env('BACKUP_MYSQL_BINARY'),
    'mysqldump_binary' => env('BACKUP_MYSQLDUMP_BINARY'),
    'retain_days' => (int) env('BACKUP_RETAIN_DAYS', 180),
    'retain_count' => (int) env('BACKUP_RETAIN_COUNT', 10),

    's3' => [
        'enabled' => (bool) env('AWS_ENABLED', false),
        'root' => env('AWS_ROOT', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded tables
    |--------------------------------------------------------------------------
    |
    | Operational tables (queues, cache, sessions) hold no application data
    | worth restoring. Their structure is still dumped so a restore recreates
    | them empty instead of leaving them missing entirely.
    */
    'exclude_tables' => [
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
    ],
];
