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
    'regulatory_discovery' => [
        // Only this reviewed official host is supported. User input cannot control the URL.
        'jdih_bpk_enabled' => env('PI_JDIH_BPK_SEARCH_ENABLED', true),
    ],
    'regulatory' => [
        'ledger_path' => env('PI_REGULATORY_LEDGER_PATH'),
        'scope_path' => env('PI_REGULATORY_SCOPE_PATH'),
    ],
];
