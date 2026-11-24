<?php

return [
    'tenant_id' => env('PI_TENANT_ID', 'ID-DIY-YOG'),
    'legacy_users_path' => env('PI_LEGACY_USERS_PATH'),
    'normative_root' => [
        'registry_path' => resource_path('pi/normative-root-r70l.json'),
    ],
    'planning_node' => [
        'registry_path' => resource_path('pi/official-planning-node-r70k.json'),
    ],
    'planning_lineage' => [
        'registry_path' => resource_path('pi/official-planning-lineage-r70j.json'),
    ],
    // R107: explicit source-only in-place rollout enables read-only annex
    // only when the owner R104 isolated archive is present in the SAME
    // Windows user profile. R105 verifies all byte/receipt pins per request.
    // Environment settings may explicitly disable this without modifying code.
    'native_annex' => [
        'enabled' => env('PI_R106_NATIVE_ANNEX_ENABLED',
            PHP_OS_FAMILY === 'Windows'
            && is_dir((string) (getenv('LOCALAPPDATA') ?: '').DIRECTORY_SEPARATOR
                .'PlanningIntelligence'.DIRECTORY_SEPARATOR.'R104_BPK_PDF_QUARANTINE')),
        'quarantine_root' => env('PI_R106_QUARANTINE_ROOT',
            (getenv('LOCALAPPDATA') ?: '').DIRECTORY_SEPARATOR
            .'PlanningIntelligence'.DIRECTORY_SEPARATOR.'R104_BPK_PDF_QUARANTINE'),
    ],
    'official_sources' => [
        'registry_path' => resource_path('pi/official-source-metadata-r100.json'),
    ],
    'development_case' => [
        'registry_path' => resource_path('pi/development-case-r85.json'),
    ],
    'regulatory_backbone' => [
        'registry_path' => resource_path('pi/regulatory-backbone-lifecycle-r86.json'),
        'catalogue_path' => resource_path('pi/regulatory-backbone-catalogue-r86.csv'),
        'catalogue_sha256' => 'BB90F07F6387589EBAC3960B899709C4758D76B6707CB72E542480FD1BA17C5B',
    ],
    'regulatory_auto_ancestry' => [
        // Local pilot: only pinned JDIH BPK sources; file cache needs no migration.
        'enabled' => env('PI_REGULATORY_AUTO_ANCESTRY_ENABLED', true),
        'cache_store' => env('PI_REGULATORY_ANCESTRY_CACHE_STORE', 'file'),
    ],
    'regulatory_discovery' => [
        // Only this reviewed official host is supported. User input cannot control the URL.
        'jdih_bpk_enabled' => env('PI_JDIH_BPK_SEARCH_ENABLED', true),
    ],
    // Admin can globally disable R89. Cloud requests additionally require
    // tenant-scoped credentials, exact preview approval and explicit consent.
    'semantic_cloud' => [
        'enabled' => env('PI_R89_CLOUD_ENABLED', false),
    ],
    'regulatory' => [
        'ledger_path' => env('PI_REGULATORY_LEDGER_PATH'),
        'scope_path' => env('PI_REGULATORY_SCOPE_PATH'),
        // Opt-in local QA snapshots: disabled until both paths are set.
        'pdf_root' => env('PI_REGULATORY_PDF_ROOT'),
        'pdf_manifest_path' => env('PI_REGULATORY_PDF_MANIFEST_PATH'),
    ],
];
