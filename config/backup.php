<?php

return [
    'directory' => env('BACKUP_DIRECTORY', storage_path('backups')),

    /*
|--------------------------------------------------------------------------
| Client Binaries
|--------------------------------------------------------------------------
|
| The "mysql" and "mysqldump" executables are auto-detected (PATH, then
| common install locations). Only set these if that detection picks the
| wrong binary on this machine.
|
*/

    'mysql_binary' => env('BACKUP_MYSQL_BINARY'),
    'mysqldump_binary' => env('BACKUP_MYSQLDUMP_BINARY'),

    /*
      |--------------------------------------------------------------------------
      | Retention
      |--------------------------------------------------------------------------
      |
      | Backups older than "retain_days" are pruned, but the newest
      | "retain_count" backups are always kept regardless of their age.
      |
      */

    'retain_days' => (int) env('BACKUP_RETAIN_DAYS', 180),
    'retain_count' => (int) env('BACKUP_RETAIN_COUNT', 10),

    /*
    |--------------------------------------------------------------------------
    | S3 Replication
    |--------------------------------------------------------------------------
    |
    | When enabled, every successful backup is also copied to the "s3"
    | filesystem disk below the given root path.
    |
    */

    's3' => [
        'enabled' => (bool) env('BACKUP_AWS_ENABLED', false),
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
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ],
];
