<?php

/*
 * Kaunter Maya checklist rule inputs (ADR-362).
 * These option lists are an initial proposal (implementer's call) and must be
 * confirmed by Jabatan Bangunan. Keys are stored in published rules; change
 * labels freely, but do not rename or remove a key that published rules use.
 */
return [
    'work_types' => [
        'baharu' => 'Bangunan baharu',
        'tambahan' => 'Tambahan / pembesaran',
        'ubahsuai' => 'Ubah suai',
        'roboh' => 'Perobohan',
        'pagar' => 'Pagar / tembok',
    ],
    'building_categories' => [
        'teres' => 'Kediaman teres',
        'berkembar' => 'Kediaman berkembar',
        'sesebuah' => 'Kediaman sesebuah',
        'komersial' => 'Komersial',
        'industri' => 'Perindustrian',
        'institusi' => 'Institusi',
        'lain' => 'Lain-lain',
    ],
    'site_conditions' => [
        'lereng_bukit' => 'Lereng bukit',
        'rizab_sungai' => 'Berhampiran rizab sungai',
        'warisan' => 'Zon warisan',
        'strata' => 'Hakmilik strata',
    ],
    // Free pre-check budgets and local tools (ADR-363). No AI, no network.
    'precheck' => [
        'ocr_max_pages' => (int) env('KAUNTER_PRECHECK_OCR_MAX_PAGES', 10),
        'process_timeout' => (int) env('KAUNTER_PRECHECK_PROCESS_TIMEOUT', 60),
        // All tool runs for one file share this budget; it must stay below the job timeout (300 s).
        'total_budget' => (int) env('KAUNTER_PRECHECK_TOTAL_BUDGET', 240),
        'min_text_chars' => (int) env('KAUNTER_PRECHECK_MIN_TEXT_CHARS', 30),
        'max_documents_per_draft' => (int) env('KAUNTER_PRECHECK_MAX_DOCUMENTS', 60),
        'pdftotext' => env('KAUNTER_PDFTOTEXT_BINARY', 'pdftotext'),
        'ghostscript' => env('KAUNTER_GHOSTSCRIPT_BINARY', PHP_OS_FAMILY === 'Windows' ? 'gswin64c' : 'gs'),
        'tesseract' => env('KAUNTER_TESSERACT_BINARY', 'tesseract'),
        'tesseract_langs' => env('KAUNTER_TESSERACT_LANGS', 'msa+eng'),
    ],
];
