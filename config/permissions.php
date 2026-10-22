<?php

return [
    'catalog' => [
        'dashboard' => ['view'],
        'registrations' => ['view', 'approve', 'reject'],
        'positions' => ['view', 'create', 'update', 'delete'],
        'permissions' => ['view', 'assign'],
        'staff' => ['view', 'create', 'update', 'deactivate', 'qr_print'],
        'storage' => ['view', 'manage'],
        'categories' => ['view', 'manage'],
        'files' => ['view', 'create', 'update', 'delete'],
        'completions' => ['request', 'review'],
        'labels' => ['print'],
        'movements' => ['borrow', 'return', 'history', 'pic_intake', 'pic_return_receive'],
        'missing' => ['manage'],
        'imports' => ['view', 'create'],
        'exports' => ['files', 'movements'],
        'audit' => ['view'],
    ],
];
