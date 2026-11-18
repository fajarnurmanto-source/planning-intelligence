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
    'development_case' => [
        'registry_path' => resource_path('pi/development-case-r85.json'),
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
    'regulatory' => [
        'ledger_path' => env('PI_REGULATORY_LEDGER_PATH'),
        'scope_path' => env('PI_REGULATORY_SCOPE_PATH'),
        // Opt-in local QA snapshots: disabled until both paths are set.
        'pdf_root' => env('PI_REGULATORY_PDF_ROOT'),
        'pdf_manifest_path' => env('PI_REGULATORY_PDF_MANIFEST_PATH'),
    ],
];
