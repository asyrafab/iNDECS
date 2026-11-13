<?php

declare(strict_types=1);

// AIRS review engine module (app/Airs). Ported from the archived Plan Review repo.
return [
    'application_version' => env('AIRS_APPLICATION_VERSION', 'v1.0.0-module'),
    'baseline_version' => env('AIRS_BASELINE_VERSION', 'v0.7.0-beta.1'),
    'release_channel' => env('AIRS_RELEASE_CHANNEL', 'beta'),
    'provider' => env('AIRS_AI_PROVIDER', 'fake'),
    'text_model' => env('AIRS_TEXT_MODEL', 'gpt-5.6-luna'),
    'vision_model' => env('AIRS_VISION_MODEL', 'gpt-5.6-luna'),
    'rule_pack' => env('AIRS_RULE_PACK', 'ljup'),
    'rule_version' => env('AIRS_RULE_VERSION', 'v2'),
    'prompt_version' => env('AIRS_PROMPT_VERSION', 'v1'),
    'report_version' => env('AIRS_REPORT_VERSION', 'v1'),
    'queue' => 'airs',
    'knowledge_path' => base_path('knowledge'),
    'python_worker' => base_path('python_worker'),

    // iNDECS application type => AIRS module. Only validated rule packs may be enabled.
    'modules' => [
        'LJUP' => ['code' => 'STREET_LIGHTING', 'rule_pack' => 'ljup', 'enabled' => true],
    ],

    'lighting_types' => [
        'BESI' => ['label' => 'Lampu besi', 'description' => 'Tiang besi/galvani atau sistem berkaitan feeder pillar.'],
        'KONKRIT' => ['label' => 'Lampu konkrit', 'description' => 'Tiang konkrit/TNB atau sistem bekalan berkaitan.'],
        'SOLAR' => ['label' => 'Lampu solar', 'description' => 'Lampu jalan solar atau komponen tenaga solar.'],
    ],

    // Advisory only: the threshold yields a recorded shadow outcome, never a rejection.
    // Paid-check fee in sen (undecided policy: unset disables payment). Only the staging driver exists.
    'fee_sen' => env('AIRS_CHECK_FEE_SEN'),
    'payment_driver' => env('AIRS_PAYMENT_DRIVER', 'staging'),

    'score_threshold' => (int) env('AIRS_SCORE_THRESHOLD', 50),

    'python' => [
        'binary' => env('AIRS_PYTHON_BINARY') ?: (PHP_OS_FAMILY === 'Windows'
            ? base_path('.venv/Scripts/python.exe')
            : base_path('.venv/bin/python')),
        'timeout_seconds' => (int) env('AIRS_PYTHON_TIMEOUT', 300),
    ],
    'limits' => [
        'upload_bytes' => (int) env('AIRS_MAX_UPLOAD_BYTES', 104857600),
        'file_bytes' => (int) env('AIRS_MAX_FILE_BYTES', 52428800),
        'direct_files' => (int) env('AIRS_MAX_DIRECT_FILES', 100),
        'archive_files' => (int) env('AIRS_MAX_ARCHIVE_FILES', 200),
        'extracted_bytes' => (int) env('AIRS_MAX_EXTRACTED_BYTES', 524288000),
        'compression_ratio' => (int) env('AIRS_MAX_COMPRESSION_RATIO', 100),
        'pages_per_case' => (int) env('AIRS_MAX_PAGES', 500),
        'ai_attempts' => (int) env('AIRS_AI_ATTEMPTS', 3),
    ],
    'reference_documents' => [
        'max_indexed_characters' => 150000,
        'max_context_characters' => 30000,
    ],
    'allowed_extensions' => ['pdf', 'png', 'jpg', 'jpeg'],
    'allowed_mime_types' => ['application/pdf', 'image/png', 'image/jpeg'],
    'stage_progress' => [
        'UPLOADED' => 0, 'EXTRACTING' => 5, 'INVENTORY' => 10, 'EXTRACTING_TEXT' => 18, 'RENDERING' => 30,
        'CLASSIFYING' => 45, 'EXTRACTING_PROFILE' => 55, 'EVALUATING_RULES' => 65, 'CONSOLIDATING' => 92,
        'GENERATING_REPORT' => 96, 'COMPLETED' => 100, 'FAILED' => 100,
    ],

    // MATRIX (Claude) document runtime. Only for paid, payment-cleared STREET_LIGHTING checks.
    'matrix' => [
        'enabled' => (bool) env('AIRS_MATRIX_ENABLED', false),
        'node' => env('AIRS_MATRIX_NODE', 'C:/Program Files/nodejs/node.exe'),
        'worker' => env('AIRS_MATRIX_WORKER', dirname(base_path()).'/MATRIX/runtime/cli.mjs'),
        'model' => env('AIRS_MATRIX_MODEL', 'claude-sonnet-5-5'),
        'timeout_seconds' => (int) env('AIRS_MATRIX_TIMEOUT_SECONDS', 180),
        'request_budget_usd' => (float) env('AIRS_MATRIX_REQUEST_BUDGET_USD', 0.25),
        'review_budget_usd' => (float) env('AIRS_MATRIX_REVIEW_BUDGET_USD', 5),
        'max_images' => 8,
        'max_image_bytes' => 5 * 1024 * 1024,
        // Candidate lessons (officer corrections) for MATRIX evaluation; kept in iNDECS storage, never in the MATRIX repo.
        'lessons_path' => env('AIRS_MATRIX_LESSONS_PATH', storage_path('app/airs/lessons')),
    ],
];
