<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the Salas API, including rate limiting,
    | versioning, and token management settings. All values can be customized
    | through environment variables for different deployment environments.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | API Version and General Settings
    |--------------------------------------------------------------------------
    */
    'prefix' => env('API_PREFIX', 'v1'),
    'token_name' => env('API_TOKEN_NAME', 'salas-api-token'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for different endpoint categories. Each limiter
    | can have multiple limit types (per minute, per hour, per day).
    | Format: [attempts, decay_minutes] or complex configuration arrays.
    |
    */
    'rate_limits' => [
        /*
        | Authentication endpoints - most restrictive
        | Used for login/token creation endpoints
        */
        'auth' => [
            'general' => [
                'max_attempts' => env('API_RATE_LIMIT_AUTH_GENERAL_ATTEMPTS', 20),
                'decay_minutes' => env('API_RATE_LIMIT_AUTH_GENERAL_DECAY', 1),
            ],
            'per_email' => [
                'max_attempts' => env('API_RATE_LIMIT_AUTH_EMAIL_ATTEMPTS', 5),
                'decay_minutes' => env('API_RATE_LIMIT_AUTH_EMAIL_DECAY', 1),
            ],
            'hourly' => [
                'max_attempts' => env('API_RATE_LIMIT_AUTH_HOURLY_ATTEMPTS', 50),
                'decay_minutes' => env('API_RATE_LIMIT_AUTH_HOURLY_DECAY', 60),
            ],
        ],

        /*
        | API endpoints for authenticated users
        | Different limits for authenticated vs guest users
        */
        'api' => [
            'authenticated' => [
                'per_minute' => [
                    'max_attempts' => env('API_RATE_LIMIT_API_AUTH_ATTEMPTS', 100),
                    'decay_minutes' => env('API_RATE_LIMIT_API_AUTH_DECAY', 1),
                ],
                'hourly' => [
                    'max_attempts' => env('API_RATE_LIMIT_API_AUTH_HOURLY_ATTEMPTS', 2000),
                    'decay_minutes' => env('API_RATE_LIMIT_API_AUTH_HOURLY_DECAY', 60),
                ],
            ],
            'guest' => [
                'per_minute' => [
                    'max_attempts' => env('API_RATE_LIMIT_API_GUEST_ATTEMPTS', 30),
                    'decay_minutes' => env('API_RATE_LIMIT_API_GUEST_DECAY', 1),
                ],
                'hourly' => [
                    'max_attempts' => env('API_RATE_LIMIT_API_GUEST_HOURLY_ATTEMPTS', 500),
                    'decay_minutes' => env('API_RATE_LIMIT_API_GUEST_HOURLY_DECAY', 60),
                ],
            ],
        ],

        /*
        | Public endpoints (read-only, no authentication required)
        | Used for publicly accessible data like categories, rooms, etc.
        */
        'public' => [
            'per_minute' => [
                'max_attempts' => env('API_RATE_LIMIT_PUBLIC_ATTEMPTS', 60),
                'decay_minutes' => env('API_RATE_LIMIT_PUBLIC_DECAY', 1),
            ],
            'hourly' => [
                'max_attempts' => env('API_RATE_LIMIT_PUBLIC_HOURLY_ATTEMPTS', 1000),
                'decay_minutes' => env('API_RATE_LIMIT_PUBLIC_HOURLY_DECAY', 60),
            ],
        ],

        /*
        | Admin endpoints - restrictive but reasonable for admin operations
        | Used for administrative functions like approval/rejection
        */
        'admin' => [
            'per_minute' => [
                'max_attempts' => env('API_RATE_LIMIT_ADMIN_ATTEMPTS', 30),
                'decay_minutes' => env('API_RATE_LIMIT_ADMIN_DECAY', 1),
            ],
            'hourly' => [
                'max_attempts' => env('API_RATE_LIMIT_ADMIN_HOURLY_ATTEMPTS', 300),
                'decay_minutes' => env('API_RATE_LIMIT_ADMIN_HOURLY_DECAY', 60),
            ],
        ],

        /*
        | Reservation endpoints - balanced limits with role-based configuration
        | Different limits for bulk users vs regular users
        */
        'reservations' => [
            'regular_user' => [
                'per_minute' => [
                    'max_attempts' => env('API_RATE_LIMIT_RESERVATIONS_USER_ATTEMPTS', 30),
                    'decay_minutes' => env('API_RATE_LIMIT_RESERVATIONS_USER_DECAY', 1),
                ],
                'hourly' => [
                    'max_attempts' => env('API_RATE_LIMIT_RESERVATIONS_USER_HOURLY_ATTEMPTS', 500),
                    'decay_minutes' => env('API_RATE_LIMIT_RESERVATIONS_USER_HOURLY_DECAY', 60),
                ],
            ],
            'bulk_user' => [
                'per_minute' => [
                    'max_attempts' => env('API_RATE_LIMIT_RESERVATIONS_BULK_ATTEMPTS', 60),
                    'decay_minutes' => env('API_RATE_LIMIT_RESERVATIONS_BULK_DECAY', 1),
                ],
                'hourly' => [
                    'max_attempts' => env('API_RATE_LIMIT_RESERVATIONS_BULK_HOURLY_ATTEMPTS', 500),
                    'decay_minutes' => env('API_RATE_LIMIT_RESERVATIONS_BULK_HOURLY_DECAY', 60),
                ],
                'daily' => [
                    'max_attempts' => env('API_RATE_LIMIT_RESERVATIONS_BULK_DAILY_ATTEMPTS', 2000),
                    'decay_minutes' => env('API_RATE_LIMIT_RESERVATIONS_BULK_DAILY_DECAY', 1440),
                ],
            ],
            'guest' => [
                'per_minute' => [
                    'max_attempts' => env('API_RATE_LIMIT_RESERVATIONS_GUEST_ATTEMPTS', 20),
                    'decay_minutes' => env('API_RATE_LIMIT_RESERVATIONS_GUEST_DECAY', 1),
                ],
                'hourly' => [
                    'max_attempts' => env('API_RATE_LIMIT_RESERVATIONS_GUEST_HOURLY_ATTEMPTS', 200),
                    'decay_minutes' => env('API_RATE_LIMIT_RESERVATIONS_GUEST_HOURLY_DECAY', 60),
                ],
            ],
            'bulk_roles' => env('API_RATE_LIMIT_RESERVATIONS_BULK_ROLES', 'bulk-importer,system-integration,admin'),
        ],

        /*
        | Bulk operations - high limits for dedicated bulk endpoints
        | Used for batch processing of multiple reservations
        */
        'bulk' => [
            'per_minute' => [
                'max_attempts' => env('API_RATE_LIMIT_BULK_ATTEMPTS', 100),
                'decay_minutes' => env('API_RATE_LIMIT_BULK_DECAY', 1),
            ],
            'hourly' => [
                'max_attempts' => env('API_RATE_LIMIT_BULK_HOURLY_ATTEMPTS', 1000),
                'decay_minutes' => env('API_RATE_LIMIT_BULK_HOURLY_DECAY', 60),
            ],
            'daily' => [
                'max_attempts' => env('API_RATE_LIMIT_BULK_DAILY_ATTEMPTS', 5000),
                'decay_minutes' => env('API_RATE_LIMIT_BULK_DAILY_DECAY', 1440),
            ],
        ],

        /*
        | Upload endpoints - restrictive to prevent abuse
        | Currently defined but not used in routes
        */
        'uploads' => [
            'per_minute' => [
                'max_attempts' => env('API_RATE_LIMIT_UPLOADS_ATTEMPTS', 10),
                'decay_minutes' => env('API_RATE_LIMIT_UPLOADS_DECAY', 1),
            ],
            'hourly' => [
                'max_attempts' => env('API_RATE_LIMIT_UPLOADS_HOURLY_ATTEMPTS', 100),
                'decay_minutes' => env('API_RATE_LIMIT_UPLOADS_HOURLY_DECAY', 60),
            ],
            'daily' => [
                'max_attempts' => env('API_RATE_LIMIT_UPLOADS_DAILY_ATTEMPTS', 500),
                'decay_minutes' => env('API_RATE_LIMIT_UPLOADS_DAILY_DECAY', 1440),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sanctum Token Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Laravel Sanctum tokens used in API authentication.
    |
    */
    'sanctum' => [
        'token_name' => env('SANCTUM_TOKEN_NAME', 'salas-api-token'),
        'token_abilities' => env('SANCTUM_TOKEN_ABILITIES', 'api:read,api:write'),
        'token_expiration' => env('SANCTUM_TOKEN_EXPIRATION_MINUTES', null), // null = no expiration
    ],

    /*
    |--------------------------------------------------------------------------
    | API Response Configuration
    |--------------------------------------------------------------------------
    |
    | General configuration for API responses and behavior.
    |
    */
    'response' => [
        'include_debug_info' => env('API_INCLUDE_DEBUG_INFO', false),
        'default_per_page' => env('API_DEFAULT_PER_PAGE', 15),
        'max_per_page' => env('API_MAX_PER_PAGE', 100),
    ],
];