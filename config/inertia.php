<?php

return [

    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
        'runtime' => env('INERTIA_SSR_RUNTIME', 'node'),
        'ensure_runtime_exists' => (bool) env('INERTIA_SSR_ENSURE_RUNTIME_EXISTS', false),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        'throw_on_error' => false,
    ],

    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [
            resource_path('js/Pages'),
            resource_path('js/pages'),
        ],
        'extensions' => [
            'js',
            'jsx',
            'ts',
            'tsx',
            'vue',
        ],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [
            resource_path('js/Pages'),
        ],
    ],

];
