<?php

return [
    'integrations' => [
        'mikrotik_driver' => env('MIKROTIK_DRIVER', 'mock'),
        'olt_driver' => env('OLT_DRIVER', 'mock'),
        'genieacs_url' => env('GENIEACS_NBI_URL', 'http://127.0.0.1:7557'),
        'dry_run' => env('DEVICE_DRY_RUN', true),
    ],
    'polling' => [
        'router_status_seconds' => env('POLL_ROUTER_STATUS_SECONDS', 300),
        'router_resources_seconds' => env('POLL_ROUTER_RESOURCES_SECONDS', 300),
        'router_active_seconds' => env('POLL_ROUTER_ACTIVE_SECONDS', 180),
        'router_network_seconds' => env('POLL_ROUTER_NETWORK_SECONDS', 600),
        'router_config_seconds' => env('POLL_ROUTER_CONFIG_SECONDS', 1800),
    ],
    'mikrotik' => [
        'connect_timeout' => env('MIKROTIK_CONNECT_TIMEOUT', 5),
        'read_timeout' => env('MIKROTIK_READ_TIMEOUT', 8),
        'connect_attempts' => env('MIKROTIK_CONNECT_ATTEMPTS', 2),
        'retry_delay_seconds' => env('MIKROTIK_RETRY_DELAY_SECONDS', 1),
        'write_operations_enabled' => false,
        'resource_retention_days' => 7,
        'interface_retention_days' => 7,
        'session_retention_days' => 30,
        'error_retention_days' => 30,
    ],
];
