<?php

return [
    'enabled' => (bool) env('MYDIGITALID_ENABLED', false),
    'enrolment_enabled' => (bool) env('MYDIGITALID_ENROLMENT_ENABLED', false),
    'login_enabled' => (bool) env('MYDIGITALID_LOGIN_ENABLED', false),
    // Real transport is deliberately unavailable until provider onboarding is confirmed.
    'network_enabled' => (bool) env('MYDIGITALID_NETWORK_ENABLED', false),
    'sandbox_confirmed' => (bool) env('MYDIGITALID_SANDBOX_CONFIRMED', false),
    'participants' => array_values(array_filter(explode(',', (string) env('MYDIGITALID_PARTICIPANTS', '')))),
    'issuer' => env('MYDIGITALID_ISSUER', ''),
    'client_id' => env('MYDIGITALID_CLIENT_ID', ''),
    'client_secret' => env('MYDIGITALID_CLIENT_SECRET', ''),
    'redirect_uri' => env('MYDIGITALID_REDIRECT_URI', ''),
    'name_claim' => env('MYDIGITALID_NAME_CLAIM', 'name'),
    'auth_method' => 'client_secret_basic',
    'key_version' => 'v1',
    'ttl' => 300,
    'idle_seconds' => 1800,
    'absolute_seconds' => 28800,
];
