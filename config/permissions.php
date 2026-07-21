<?php

return [
    'catalog' => [
        'dashboard' => ['view'],
        'registrations' => ['view', 'approve', 'reject'],
        'positions' => ['view', 'create', 'update', 'delete'],
        'permissions' => ['view', 'assign'],
        'staff' => ['view', 'create', 'update', 'deactivate', 'qr_print'],
        'storage' => ['view', 'manage'],
        'files' => ['view', 'create', 'update', 'archive'],
        'labels' => ['print'],
        'movements' => ['borrow', 'return', 'history'],
        'missing' => ['manage'],
        'imports' => ['view', 'create'],
        'exports' => ['files', 'movements'],
        'audit' => ['view'],
    ],
];
