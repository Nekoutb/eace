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
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Livewire temporary uploads. Deliberately NOT listed in
        // tenancy.filesystem.disks, so it stays a single CENTRAL location: the
        // root string is captured at boot (central context) and never re-rooted
        // per tenant. Without this, temp uploads land on the tenant-scoped
        // `local` disk and validation's getSize() throws UnableToRetrieveMetadata.
        'livewire-tmp' => [
            'driver' => 'local',
            'root' => storage_path('app/livewire-tmp'),
            'throw' => false,
            'report' => false,
        ],

        // Shared, landlord-owned storage. Like livewire-tmp, deliberately NOT in
        // tenancy.filesystem.disks, so it stays a single CENTRAL location across
        // all tenants — used for the cross-customer ACF authentication cache
        // (one canonical Harmony2 screenshot per ACF reference, reused by every
        // workspace that checks the same certificate).
        'central' => [
            'driver' => 'local',
            'root' => storage_path('app/central'),
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
            'throw' => false,
            'report' => false,
        ],

        // Wasabi (S3-compatible) — immutable, encrypted document storage for
        // compliance evidence, invoices, ACFs and manifests. Object Lock is set
        // per-object by EvidenceStore (it cannot be a disk-wide default), so the
        // disk itself is a plain private S3 mount. Region drives the endpoint:
        // https://s3.<region>.wasabisys.com.
        'wasabi' => [
            'driver' => 's3',
            'key' => env('WASABI_KEY'),
            'secret' => env('WASABI_SECRET'),
            'region' => env('WASABI_REGION', 'us-east-1'),
            'bucket' => env('WASABI_BUCKET'),
            'endpoint' => env('WASABI_ENDPOINT', 'https://s3.'.env('WASABI_REGION', 'us-east-1').'.wasabisys.com'),
            'use_path_style_endpoint' => (bool) env('WASABI_PATH_STYLE', false),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
            // Hard timeouts so a stalled Wasabi connection can NEVER hang a request
            // indefinitely (a missing timeout once hung a page for 20 minutes). The
            // total is generous enough for the ~52 MB taxpayer-register upload.
            'http' => [
                'connect_timeout' => (float) env('WASABI_CONNECT_TIMEOUT', 5),
                'timeout' => (float) env('WASABI_TIMEOUT', 120),
            ],
            'retries' => (int) env('WASABI_RETRIES', 1),
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
