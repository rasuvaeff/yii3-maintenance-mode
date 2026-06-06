# rasuvaeff/yii3-maintenance-mode

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-maintenance-mode.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-maintenance-mode.svg)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-maintenance-mode/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-maintenance-mode/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![Coverage](https://codecov.io/gh/rasuvaeff/yii3-maintenance-mode/branch/master/graph/badge.svg)](https://codecov.io/gh/rasuvaeff/yii3-maintenance-mode)
[![Psalm Level](https://img.shields.io/badge/Psalm-1-blue.svg)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-maintenance-mode/php)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-maintenance-mode.svg)](LICENSE.md)

Maintenance mode PSR-15 middleware for Yii3. Returns HTTP 503 with `Retry-After` header. Supports IP allow-list, bypass token, JSON and HTML responses.

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference ready to paste into context.

## Requirements

- PHP 8.3+
- `psr/http-message` ^2.0
- `psr/http-server-middleware` ^1.0

## Installation

```bash
composer require rasuvaeff/yii3-maintenance-mode
```

## Usage

### 1. Add middleware to the pipeline

`MaintenanceMiddleware` must be placed **as early as possible** — before routing and authentication. Otherwise requests reach the router even during maintenance.

```php
// config/web.php or wherever your middleware stack is defined
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;

return [
    MaintenanceMiddleware::class,   // ← first
    ErrorCatcher::class,
    Router::class,
    // ...
];
```

### 2. Configure in params.php

```php
// config/params.php
return [
    'rasuvaeff/yii3-maintenance-mode' => [
        'enabled'         => false,
        'retryAfter'      => 300,
        'allowedIps'      => [],
        'bypassTokenHash' => '',
    ],
];
```

The package ships `config/di.php` and `config/params.php` via config-plugin. `ConfigMaintenanceProvider` and `MaintenanceMiddleware` are registered automatically.

### 3. Enable maintenance mode

**Via environment variable** (no deploy required):

```php
// config/params.php
'rasuvaeff/yii3-maintenance-mode' => [
    'enabled'         => (bool) ($_ENV['MAINTENANCE_ENABLED'] ?? false),
    'retryAfter'      => 600,
    'allowedIps'      => ['10.0.0.1'],
    'bypassTokenHash' => $_ENV['MAINTENANCE_BYPASS_HASH'] ?? '',
],
```

**Via JSON file** (toggle without deploy or restart):

```php
// config/di.php — switch to FileMaintenanceProvider
use Rasuvaeff\Yii3MaintenanceMode\FileMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceProvider;

return [
    MaintenanceProvider::class => [
        'class' => FileMaintenanceProvider::class,
        '__construct()' => [
            'filePath' => dirname(__DIR__) . '/maintenance.json',
        ],
    ],
];
```

Enable: `echo '{"enabled":true,"retryAfter":600}' > maintenance.json`  
Disable: `rm maintenance.json`

## Bypass token

Generate a hash of your secret token:

```bash
php -r "echo hash('sha256', 'my-secret-token');"
# 9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08
```

Store the hash in config (never the token itself):

```php
'bypassTokenHash' => '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08',
```

Access any URL by appending `?bypass=my-secret-token`:

```
https://example.com/?bypass=my-secret-token
https://example.com/admin/dashboard?bypass=my-secret-token
```

Token is compared with `hash_equals()` — timing-safe, no brute-force risk.

## API reference

### `MaintenanceState`

```php
readonly class MaintenanceState {
    public bool   $enabled         = false;
    public int    $retryAfter      = 300;   // seconds
    /** @var list<string> */
    public array  $allowedIps      = [];
    public string $bypassTokenHash = '';    // sha256 of bypass token
}
```

### `MaintenanceProvider` (interface)

```php
interface MaintenanceProvider {
    public function getState(): MaintenanceState;
}
```

Implement this to create custom providers (DB, Redis, feature flag, etc.).

### `ConfigMaintenanceProvider`

```php
$provider = new ConfigMaintenanceProvider([
    'enabled'         => true,
    'retryAfter'      => 600,
    'allowedIps'      => ['127.0.0.1', '10.0.0.1'],
    'bypassTokenHash' => hash('sha256', 'secret'),
]);
```

State is immutable — set once at construction. Best for config/env sources.

### `FileMaintenanceProvider`

```php
$provider = new FileMaintenanceProvider(filePath: '/var/app/maintenance.json');
```

Reads state on every `getState()` call — changes take effect without restart.  
Returns disabled state (`enabled: false`) when file is missing or invalid JSON.

`maintenance.json` format:

```json
{
    "enabled": true,
    "retryAfter": 600,
    "allowedIps": ["10.0.0.1"],
    "bypassTokenHash": "9f86d081..."
}
```

### `MaintenanceMiddleware`

```php
$middleware = new MaintenanceMiddleware(
    provider: $provider,
    responseFactory: $responseFactory,
);
```

Decision logic (in order):

| Condition | Action |
|---|---|
| `enabled === false` | Pass through |
| `REMOTE_ADDR` in `allowedIps` | Pass through |
| Valid `?bypass=` token | Pass through |
| Otherwise | Return 503 |

## Response format

### JSON (API clients)

Returned when `Accept: application/json` or `Accept` header is absent:

```http
HTTP/1.1 503 Service Unavailable
Content-Type: application/json
Retry-After: 600
```

```json
{
    "error": "Service Unavailable",
    "message": "The server is currently undergoing maintenance.",
    "retryAfter": 600
}
```

### HTML (browsers)

Returned when `Accept: text/html` or any other non-JSON accept:

```http
HTTP/1.1 503 Service Unavailable
Content-Type: text/html; charset=utf-8
Retry-After: 600
```

A minimal HTML maintenance page is returned. Override by implementing your own `MaintenanceProvider` wrapper or `MiddlewareInterface` decorator.

## Security

- Bypass token compared with `hash_equals()` — timing-safe
- Only the hash is stored, never the plaintext token
- IP allow-list uses strict string comparison on `REMOTE_ADDR`
- `FileMaintenanceProvider` catches `JsonException` gracefully — invalid file → disabled state (safe default)

## Examples

See [`examples/`](examples/) for detailed Yii3 wiring: providers, pipeline placement, bypass token, console command, env-based toggling.

## Development

```bash
make install
make build
make cs-fix
make test
make test-coverage
make mutation
make release-check
```

`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
