<?php

return [
    'company' => [
        'name' => env('COMPANY_NAME','MADINA IMPORT'),
        'email' => env('COMPANY_EMAIL','contact@madina-import.mg'),
        'contact' => env('COMPANY_CONTACT','+261 00 00 000 00'),
        'address' => env('COMPANY_ADDRESS','Antananarivo, Madagascar'),
    ],
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
