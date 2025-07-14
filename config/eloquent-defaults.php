<?php

// config for dayemsiddiqui/EloquentDefaults
return [

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery Settings
    |--------------------------------------------------------------------------
    |
    | These settings control the automatic discovery of models using the
    | HasEloquentDefaults trait. The scanner will look for models in the
    | specified directories during application boot.
    |
    */

    'auto_discovery' => [

        /*
        | Enable or disable auto-discovery of models with HasEloquentDefaults trait.
        | When enabled, the package will automatically scan for and register models.
        */
        'enabled' => true,

        /*
        | Directories to scan for models using HasEloquentDefaults trait.
        | Paths are relative to the application base path.
        */
        'scan_directories' => [
            'app/Models',
            'app',
        ],

        /*
        | Additional directories to scan. Use this to add custom model locations
        | without overriding the default directories.
        */
        'additional_directories' => [],

        /*
        | Directories to exclude from scanning. Useful for skipping large
        | directories that you know don't contain relevant models.
        */
        'exclude_directories' => [
            'app/Console',
            'app/Http/Middleware',
            'app/Http/Controllers',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Settings
    |--------------------------------------------------------------------------
    |
    | Auto-discovery can be cached for better performance. In production,
    | discovered models are cached by default. In development, caching
    | is disabled for better developer experience.
    |
    */

    'cache' => [

        /*
        | Cache key for storing discovered models. Change this if you have
        | cache key conflicts with other packages.
        */
        'key' => 'eloquent_defaults.discovered_models',

        /*
        | Cache TTL in seconds. How long to cache discovered models.
        | Set to 0 to cache forever (until manually cleared).
        */
        'ttl' => 3600, // 1 hour

        /*
        | Force caching even in development environment.
        | Normally caching is disabled in development for better DX.
        */
        'force_in_development' => false,

    ],

];
