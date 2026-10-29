<?php

return [
    // MBSP bounding box, EPSG:4326: [min lon, min lat, max lon, max lat].
    'region_bbox' => [100.33, 5.10, 100.60, 5.62],

    'ogr2ogr' => env('GIS_OGR2OGR', 'ogr2ogr'),

    // Credentials stay in .env only. Anonymous queries work; a token is optional.
    'arcgis' => [
        'portal_url' => env('ARCGIS_PORTAL_URL', 'https://spgis.mbsp.gov.my/portal'),
        'username' => env('ARCGIS_USERNAME'),
        'password' => env('ARCGIS_PASSWORD'),
    ],

    'imagery' => [
        'imagery' => [
            'label' => 'Imej satelit',
            'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            'attribution' => 'Esri, Maxar, Earthstar Geographics',
        ],
        'street' => [
            'label' => 'Peta jalan',
            'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',
            'attribution' => 'Esri, HERE, Garmin, OpenStreetMap contributors',
        ],
        'orthophoto' => [
            'label' => 'Ortofoto MBSP',
            'url' => env('ORTHOPHOTO_TILE_URL', ''),
            'attribution' => 'MBSP',
        ],
    ],

    // Initial layer definitions, seeded into core.gis_layers by GisLayerSeeder (Task 2).
    // Task 2 adds the lots, zoning and utilities layers after discovering their service URLs.
    'layers' => [
        [
            'id' => 'legacy_mjkp',
            'label' => 'Keputusan terdahulu',
            'kind' => 'legacy_papers',
            'source_url' => 'https://spgis.mbsp.gov.my/geoportal/rest/services/DEMARCATION/MJKP_eOSC/FeatureServer/1204',
            'where_clause' => '1=1',
            'is_active' => true,
            'sort_order' => 90,
            'match_attributes' => null,
        ],
    ],
];
