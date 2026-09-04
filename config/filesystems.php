<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver.
    |
    | Every disk here uses the "local" driver, and that is the whole list of
    | drivers this application can resolve: no adapter package for any other
    | one is installed. Flysystem 3 ships the local adapter as a separate
    | package (league/flysystem-local), and the s3, ftp and sftp adapters are
    | separate packages too - none of which is in composer.json.
    |
    | TODO 37 removed an "s3" entry that had been sitting here since the
    | framework's own default configuration. It had never been reachable: no
    | league/flysystem-aws-s3-v3 in the tree, and no call site anywhere in the
    | application. Adding a cloud disk means adding its adapter package first.
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ],
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // The root is DELIBERATELY public_path(), not the previous bare
        // 'public' relative path. The latter resolved against PHP's working
        // directory, which in a web request is the public/ directory (i.e.
        // public/public/), but under artisan or a queue worker is the
        // project root (i.e. public/) - the same disk wrote to two
        // different places depending on who called it. The view's URL
        // prefix (messages.blade.php) moved along with it.
        'web' => [
            'driver' => 'local',
            'root' => public_path(),
            'url' => env('APP_URL'),
            'visibility' => 'public',
            'throw' => false,
        ],

        'news_files' => [
            'driver' => 'local',
            'root' => storage_path('app/private/news_files'),
            // 'url' => env('APP_URL').'/storage/news_files',
            'visibility' => 'private',
            'throw' => false,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ],
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
        // public_path('storage/news_files') => storage_path('app/private/news_files'),
    ],

];
