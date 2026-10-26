<?php

return [
    'development_admin' => [
        'name' => env('LRMS_ADMIN_NAME'),
        'email' => env('LRMS_ADMIN_EMAIL'),
        'password' => env('LRMS_ADMIN_PASSWORD'),
    ],

    'storage' => [
        'room_name' => env('LRMS_STORAGE_ROOM_NAME', 'Main File Room'),
    ],

    // Session unlock for /entry/ai-usage (cleared on logout).
    'ai_usage_password' => env('LRMS_AI_USAGE_PASSWORD', 'YKN@2026'),

    // Session unlock for /entry/manage-operators (cleared on logout).
    'manage_operator_password' => env('LRMS_MANAGE_OPERATOR_PASSWORD', 'YKN@2026'),

    // USD → MYR for AI cost display (Google bills in USD).
    'ai_usage_usd_myr' => (float) env('LRMS_USD_MYR_RATE', 4.20),

    // Google AI paid standard tier, USD per 1M tokens (update when Google reprices).
    'ai_usage_rates' => [
        'default' => [
            'input' => 0.25,
            'output' => 1.50,
            'cached' => 0.025,
        ],
        'models' => [
            'gemini-3.1-flash-lite' => [
                'input' => 0.25,
                'output' => 1.50,
                'cached' => 0.025,
            ],
            'gemini-3.5-flash-lite' => [
                'input' => 0.30,
                'output' => 2.50,
                'cached' => 0.03,
            ],
            'gemini-3.6-flash' => [
                'input' => 0.75,
                'output' => 3.75,
                'cached' => 0.075,
            ],
            'gemini-3.8-flash' => [
                'input' => 0.75,
                'output' => 3.75,
                'cached' => 0.075,
            ],
        ],
    ],
];



