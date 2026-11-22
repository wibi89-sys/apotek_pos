<?php

declare(strict_types=1);

return [
    'public_key' => env('LICENSE_PUBLIC_KEY'),
    'cloud_hub_url' => env('LICENSE_SERVER_URL', env('CLOUD_HUB_URL', 'http://127.0.0.1:8000')),
    'license_file_path' => storage_path('app/private/license.lic'),
    'mock_hwid' => env('LICENSE_HWID_MOCK'),
    'terminal_id' => env('TERMINAL_ID', 'POS01'),
    'branch_id' => env('BRANCH_ID', 'BR01'),
];
