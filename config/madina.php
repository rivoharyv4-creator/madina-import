<?php

return [
    'company' => [
        'name' => env('COMPANY_NAME','MADINA IMPORT'),
        'email' => env('COMPANY_EMAIL','contactmadinaimport@gmail.com'),
        'contact' => env('COMPANY_CONTACT','+261 34 98 732 08'),
        'whatsapp' => env('COMPANY_WHATSAPP','+86 158 0200 3702'),
        'address' => env('COMPANY_ADDRESS','Ambatomainty Lot 102 B Bis Antananarivo'),
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
