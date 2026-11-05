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
];
