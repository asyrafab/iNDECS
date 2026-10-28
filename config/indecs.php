<?php

return [
    'document_scanner_database' => env('INDECS_DOCUMENT_SCANNER_DATABASE'),
    'document_scanner_certificates' => env('INDECS_DOCUMENT_SCANNER_CERTIFICATES'),
    'document_scanner_binary' => env('INDECS_DOCUMENT_SCANNER_BINARY', 'clamscan'),
    'staff_auth_driver' => env('INDECS_STAFF_AUTH_DRIVER', 'mbsp'),
    'demo_password' => env('INDECS_DEMO_PASSWORD'),
];
