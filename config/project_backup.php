<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Project backup
    |--------------------------------------------------------------------------
    |
    | A lightweight backup of the project files and the MySQL database. It runs
    | from inside the application on the first visit after the configured time,
    | so no Windows Task Scheduler entry or external cron is required.
    |
    */

    'enabled' => env('PROJECT_BACKUP_ENABLED', true),

    // Keep this outside the project directory so an archive never contains itself.
    'path' => env('PROJECT_BACKUP_PATH', 'C:/Backups/psych_pharmacy'),

    // Optional second location (external drive, network share, ...).
    'external_path' => env('PROJECT_BACKUP_EXTERNAL_PATH'),

    'keep_days' => (int) env('PROJECT_BACKUP_KEEP_DAYS', 14),

    'run_after_hour' => (int) env('PROJECT_BACKUP_RUN_AFTER_HOUR', 2),
    'run_after_minute' => (int) env('PROJECT_BACKUP_RUN_AFTER_MINUTE', 30),

    'mysqldump' => env(
        'PROJECT_BACKUP_MYSQLDUMP',
        'C:/Program Files/MySQL/MySQL Server 9.6/bin/mysqldump.exe'
    ),

    'timeout' => (int) env('PROJECT_BACKUP_TIMEOUT', 300),

    /*
    | Files and folders that are never copied. vendor/ and node_modules/ are
    | restored with composer install and npm install. Logs and framework
    | caches are large and carry no data worth restoring.
    */
    'exclude' => [
        'vendor',
        'node_modules',
        '.git',
        'storage/logs',
        'storage/framework',
        'storage/app/backups',
        'storage/app/backup-temp',
        'storage/pail',
        'storage/debugbar',
        'public/hot',
        'public/storage',
        '.phpunit.cache',
        '.phpunit.result.cache',
        '.idea',
        '.vscode',
        '.fleet',
        '.nova',
        '.zed',
    ],

];
