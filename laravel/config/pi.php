<?php

return [
    'tenant_id' => env('PI_TENANT_ID', 'ID-DIY-YOG'),
    'legacy_users_path' => env('PI_LEGACY_USERS_PATH'),
    'regulatory' => [
        'ledger_path' => env('PI_REGULATORY_LEDGER_PATH'),
        'scope_path' => env('PI_REGULATORY_SCOPE_PATH'),
    ],
];
