<?php

return [

    'default' => 'default',

    'documentations' => [

        'default' => [
            'api' => [
                'title' => 'Wallet Service API',
            ],

            'routes' => [
                /*
                 * Swagger UI route
                 */
                'api' => 'api/docs',
            ],

            'paths' => [

                'use_absolute_path' => true,

                'swagger_ui_assets_path' => 'vendor/swagger-api/swagger-ui/dist/',

                /*
                 * JSON output file
                 */
                'docs_json' => 'api-docs.json',

                /*
                 * YAML output file
                 */
                'docs_yaml' => 'openapi.yaml',

                /*
                 * This tells Swagger which file to use in the UI
                 */
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'yaml'),

                /*
                 * We are NOT scanning PHP annotations
                 */
                'annotations' => [
                    base_path(),
                ],
            ],
        ],
    ],

    'defaults' => [

        'routes' => [

            /*
             * Route where JSON/YAML is served from
             */
            'docs' => 'api/docs',

            'oauth2_callback' => 'api/oauth2-callback',

            'middleware' => [
                'api' => [],
                'asset' => [],
                'docs' => [],
                'oauth2_callback' => [],
            ],

            'group_options' => [],
        ],

        'paths' => [

            /*
             * Where Swagger files are stored
             */
            'docs' => storage_path('api-docs'),

            'views' => base_path('resources/views/vendor/l5-swagger'),

            'base' => env('L5_SWAGGER_BASE_PATH', null),

            'excludes' => [],
        ],

        'scanOptions' => [
            'analyser' => null,
            'analysis' => null,
            'processors' => [],
            'pattern' => '*.php',
            'exclude' => [
                base_path('vendor'),
                base_path('app'),
                base_path('routes'),
            ],
        ],

        /*
         * ✅ SECURITY SCHEMES FOR JWT + API KEY
         */
        'securityDefinitions' => [

            'securitySchemes' => [

                'jwt' => [
                    'type' => 'apiKey',
                    'description' => 'JWT Authorization header. Example: Bearer {token}',
                    'name' => 'Authorization',
                    'in' => 'header',
                ],

                'api_key' => [
                    'type' => 'apiKey',
                    'description' => 'API Key for wallet requests',
                    'name' => 'X-API-KEY',
                    'in' => 'header',
                ],
            ],

            'security' => [
                ['jwt' => []],
                ['api_key' => []],
            ],
        ],

        /*
         * ✅ DO NOT AUTO GENERATE (YOU USE YAML)
         */
        'generate_always' => false,

        'generate_yaml_copy' => false,

        'proxy' => false,

        'additional_config_url' => null,

        'operations_sort' => null,

        'validator_url' => null,

        'ui' => [
            'display' => [
                'dark_mode' => false,
                'doc_expansion' => 'none',
                'filter' => true,
            ],

            'authorization' => [
                'persist_authorization' => true,

                'oauth2' => [
                    'use_pkce_with_authorization_code_grant' => false,
                ],
            ],
        ],

        'constants' => [
            'L5_SWAGGER_CONST_HOST' => env('APP_URL', 'http://localhost:8000'),
        ],
    ],
];