<?php

declare(strict_types=1);

return [
    'rasuvaeff/yii3-maintenance-mode' => [
        'enabled' => false,
        'retryAfter' => 300,
        'allowedIps' => [],
        'bypassTokenHash' => '',
        'access' => [
            'excludedPathPrefixes' => [],
            'bypassHeader' => 'X-Maintenance-Bypass',
            'bypassQueryParameter' => 'bypass',
            'allowQueryParameter' => true,
        ],
    ],
];
