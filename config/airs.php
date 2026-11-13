<?php

return [
    'enabled' => (bool) env('AIRS_ENABLED', false),
    'url' => env('AIRS_SERVICE_URL', ''),
    'token' => env('AIRS_SERVICE_TOKEN', ''),
    'queue' => 'airs',
    // Mappings do not activate unvalidated rule packs in AIRS.
    'modules' => ['PB' => 'BUILDING_PLAN', 'PBK' => 'BUILDING_PLAN', 'PBS' => 'BUILDING_PLAN', 'PMT' => 'BUILDING_PLAN', 'TEL' => 'BUILDING_PLAN', 'PKM' => 'PLANNING_PERMISSION', 'LJUP' => 'STREET_LIGHTING'],
];
