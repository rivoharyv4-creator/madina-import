<?php

return [
    'backup_path' => env('BACKUP_PATH') ?: storage_path('app/backups'),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'persistent_directories' => [
        'products',
        'payments',
        'expenses',
        'invoices',
        'quotes',
        'logistics',
        'exports',
    ],
];
