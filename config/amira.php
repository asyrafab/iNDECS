<?php

/*
 * AMIRA conversation (ADR-369). iNDECS calls the AMIRA backend server-side only; the token never reaches a browser.
 * The model itself (LM Studio now, production server later) is configured in the AMIRA backend, not here.
 */
return [
    'enabled' => (bool) env('AMIRA_ENABLED', false),
    'url' => env('AMIRA_SERVICE_URL', 'http://127.0.0.1:8770'),
    'token' => env('AMIRA_SERVICE_TOKEN'),
    'timeout' => (int) env('AMIRA_TIMEOUT', 150),
    'daily_limit' => (int) env('AMIRA_DAILY_LIMIT', 30),
    'max_chars' => 1000,
    'history' => 6,
    'sources' => 4,
    'excerpt_chars' => 1200,
    'retention_months' => (int) env('AMIRA_RETENTION_MONTHS', 12),
    'poll_minutes' => 5,
    // Common Malay chat words ignored when searching the library.
    'stopwords' => ['saya', 'nak', 'apa', 'yang', 'dan', 'untuk', 'ini', 'itu', 'dengan', 'boleh', 'ada', 'tak', 'tidak', 'perlu', 'macam', 'mana',
        'kena', 'kalau', 'jika', 'atau', 'pun', 'lah', 'kah', 'dia', 'kami', 'kita', 'anda', 'awak', 'sahaja', 'juga', 'dalam', 'pada', 'dari', 'daripada'],
];
