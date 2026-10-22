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
];
