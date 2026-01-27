<?php

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],

        'admin' => [
            'driver' => 'sanctum',
            'provider' => 'admins',
        ],

        'engineer' => [  // Singular for the guard name
            'driver' => 'sanctum',
            'provider' => 'engineers',
        ],

        'storekeeper' => [  // Singular for the guard name
            'driver' => 'sanctum',
            'provider' => 'storekeepers',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\V1\Admin::class,
        ],

        'engineers' => [
            'driver' => 'eloquent',
            'model' => App\Models\V1\Engineer::class,
        ],

        'storekeepers' => [
            'driver' => 'eloquent',
            'model' => App\Models\V1\Storekeeper::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'admins' => [
            'provider' => 'admins',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'engineers' => [
            'provider' => 'engineers',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'storekeepers' => [
            'provider' => 'storekeepers',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
