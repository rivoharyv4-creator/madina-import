<?php

return [
    'company' => [
        'name' => env('COMPANY_NAME','MADINA IMPORT'),
        'email' => env('COMPANY_EMAIL','contactmadinaimport@gmail.com'),
        'contact' => env('COMPANY_CONTACT','+261 34 98 732 08'),
        'whatsapp' => env('COMPANY_WHATSAPP','+86 158 0200 3702'),
        'address' => env('COMPANY_ADDRESS','Lot IIB 106 Ambatomainty Antananarivo'),
        'timezone' => env('COMPANY_TIMEZONE','Asia/Shanghai'),
        'nif' => env('COMPANY_NIF','4019196145'),
        'rcs' => env('COMPANY_RCS','2025B00524'),
        'stat' => env('COMPANY_STAT','46101 11 2025 0 10528'),
    ],
    'public' => [
        'madagascar_phone' => env('PUBLIC_MADAGASCAR_PHONE', '+261 34 98 732 08'),
        'china_phone' => env('PUBLIC_CHINA_PHONE', '+86 158 0200 3702'),
        'whatsapp' => env('PUBLIC_WHATSAPP', '+261349873208'),
        'facebook_url' => env('PUBLIC_FACEBOOK_URL', 'https://www.facebook.com/profile.php?id=61553409549693'),
    ],
    'admin_login_path' => env('ADMIN_LOGIN_PATH', 'madina-gestion-e2e26c5871bf4033b6ee1a4769e47ff7'),
    'backup_path' => env('BACKUP_PATH') ?: storage_path('app/backups'),
    'backup_retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'persistent_directories' => [
        'products',
        'payments',
        'expenses',
        'invoices',
        'quotes',
        'receipts',
        'logistics',
        'exports',
    ],
];
