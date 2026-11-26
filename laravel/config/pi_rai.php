<?php
return [
    'enabled'=>(bool) env('PI_RAI_ENABLED',false),
    'discovery_enabled'=>(bool) env('PI_RAI_DISCOVERY_ENABLED',false),
    'freshness_enabled'=>(bool) env('PI_RAI_FRESHNESS_ENABLED',false),
    'repository_root'=>env('PI_RAI_REPOSITORY_ROOT'),
];
