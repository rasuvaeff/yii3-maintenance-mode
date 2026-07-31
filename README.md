# rasuvaeff/yii3-maintenance-mode

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-maintenance-mode.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-maintenance-mode.svg)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-maintenance-mode/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-maintenance-mode/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![Psalm Level](https://img.shields.io/badge/Psalm-1-blue.svg)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-maintenance-mode/php)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-maintenance-mode.svg)](LICENSE.md)
[Русская версия](README.ru.md)

Maintenance mode PSR-15 middleware for Yii3. Returns HTTP 503 with `Retry-After` header. Supports IP allow-list, bypass token, JSON and HTML responses.

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference ready to paste into context.

## Requirements

- PHP 8.3 – 8.5
- `psr/http-message` ^2.0
- `psr/http-server-middleware` ^1.0
- `psr/http-server-handler` ^1.0
- `psr/http-factory` ^1.0 and a PSR-17 implementation — `MaintenanceMiddleware` takes a `ResponseFactoryInterface`

## Installation

```bash
composer require rasuvaeff/yii3-maintenance-mode
```

## Usage

### 1. Add middleware to the pipeline

`MaintenanceMiddleware` must run **before routing and authentication** — otherwise requests reach the router even during maintenance. It must **not** be the outermost middleware: the global error catcher, request ID and observability middleware have to wrap it, or an error raised while maintenance is served is returned without them.

```php
// config/web.php or wherever your middleware stack is defined
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;

return [
    ErrorCatcher::class,            // ← outermost: wraps everything below
    // request ID, logging and tracing, security headers …
    MaintenanceMiddleware::class,   // ← before routing and auth
    Router::class,
    // ...
];
```

In a Yii3 middleware stack the first element is the outermost one: everything listed above `MaintenanceMiddleware` wraps it, everything below it is skipped while maintenance is on.

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

The package ships `config/di.php` and `config/params.php`. They are merged automatically **only when the root application uses the `yiisoft/config` plugin** — that is the case for `yiisoft/app` and `yiisoft/app-api`. Without the plugin nothing is registered for you, and the services have to be wired by hand:

```php
use Psr\Http\Message\ResponseFactoryInterface;
use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;

$middleware = new MaintenanceMiddleware(
    provider: new ConfigMaintenanceProvider($config),
    responseFactory: $container->get(ResponseFactoryInterface::class),
);
```

### 3. Enable maintenance mode

**Via environment variable** (no deploy required):

```php
// config/params.php
'rasuvaeff/yii3-maintenance-mode' => [
    'enabled'         => filter_var($_ENV['MAINTENANCE_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
    'retryAfter'      => 600,
    'allowedIps'      => ['10.0.0.1'],
    'bypassTokenHash' => (string) ($_ENV['MAINTENANCE_BYPASS_HASH'] ?? ''),
],
```

Parse the flag with `filter_var(..., FILTER_VALIDATE_BOOL)`, never with a `(bool)` cast: environment variables are strings, and `(bool) 'false'`, `(bool) '0.0'` and `(bool) 'off'` are all `true` — a cast turns "maintenance off" into "maintenance on".

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

The file is re-read on every request, so it must never be observed half-written. Write a temporary file **in the same directory** and move it into place — `rename()` within one filesystem is atomic, a plain `echo > maintenance.json` is not:

```bash
dir=/var/www/app

# enable
tmp="$(mktemp "$dir/maintenance.json.XXXXXX")"
printf '%s' '{"enabled":true,"retryAfter":600}' > "$tmp"
chmod 0644 "$tmp"
mv -f "$tmp" "$dir/maintenance.json"

# disable
tmp="$(mktemp "$dir/maintenance.json.XXXXXX")"
printf '%s' '{"enabled":false}' > "$tmp"
chmod 0644 "$tmp"
mv -f "$tmp" "$dir/maintenance.json"
```

Deleting the file disables maintenance mode too, because a missing file is read as the default disabled state. An explicit `{"enabled":false}` is preferable: it tells "deliberately off" apart from "the file vanished".

## Bypass token

Generate a long random token and store only its hash:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"          # the token itself
php -r "echo hash('sha256', 'my-secret-token'), PHP_EOL;"  # what goes into config
# ea5add57437cbf20af59034d7ed17968dcc56767b41965fcc5b376d45db8b4a3
```

Store the hash in config (never the token itself):

```php
'bypassTokenHash' => 'ea5add57437cbf20af59034d7ed17968dcc56767b41965fcc5b376d45db8b4a3',
```

Access any URL by appending `?bypass=my-secret-token`:

```
https://example.com/?bypass=my-secret-token
https://example.com/admin/dashboard?bypass=my-secret-token
```

The token is compared with `hash_equals()`, which removes the timing side channel. That is not brute-force protection: use at least 32 random bytes, and rate-limit at the web server if the maintenance page is publicly reachable.

**The query parameter travels inside the URL.** It lands in web-server access logs, application logs, the `Referer` header of outgoing links, browser history and every proxy in between. Serve the site over TLS, treat the token as burnt once it has been used on a shared machine, and rotate it after the maintenance window. A header-based transport does not exist in 1.0.x.

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

Implement this to create custom providers (DB, Redis, feature flag, etc.). A provider supplies state only — it has no influence on the response body.

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

`maintenance.json` format:

```json
{
    "enabled": true,
    "retryAfter": 600,
    "allowedIps": ["10.0.0.1"],
    "bypassTokenHash": "ea5add57437cbf20af59034d7ed17968dcc56767b41965fcc5b376d45db8b4a3"
}
```

**Failure behaviour is fail-open.** A missing file, an unreadable file and invalid JSON all yield the default state — `enabled: false` — so traffic keeps flowing. That is convenient (a lost file cannot lock you out) and dangerous (a typo in a deploy script silently cancels the maintenance window). Values are coerced, not validated: `"retryAfter": "soon"` becomes `0`. Verify after writing — `curl -sI https://example.com/ | head -1` must report `503`.

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

A minimal HTML maintenance page is returned. In 1.0.x the body is not configurable: a custom `MaintenanceProvider` supplies state, not markup. To brand the page, wrap the middleware into your own `MiddlewareInterface` and rewrite the body of the 503 it returns.

## Security

- The bypass token is compared with `hash_equals()` — no timing side channel. Brute-force protection is the application's job.
- Only the SHA-256 hash is stored, never the plaintext token.
- The bypass token travels as a query parameter and is therefore logged by every hop. Use TLS and rotate it.
- The IP allow-list is an exact string match against `REMOTE_ADDR`: no CIDR, no ranges, no IPv6 normalization (`::1` and `0:0:0:0:0:0:0:1` are different strings).
- Behind a reverse proxy, load balancer or CDN `REMOTE_ADDR` holds the proxy address, so the allow-list matches either every request or none. The package deliberately ignores `X-Forwarded-For`: trusting it without a trusted-proxy allow-list would let any client spoof an allowed address. Enforce the allow-list on the proxy instead.
- `FileMaintenanceProvider` is fail-open: an unreadable or malformed file leaves the site online (see above).

## Limitations in 1.0.x

Not supported: header-based bypass tokens, path exclusions for health and readiness endpoints, CIDR matching, trusted-proxy client IP resolution, custom response factories, an atomic state writer and a console command. Do not configure them — they are not part of this release.

## Examples

See [`examples/`](examples/) for detailed Yii3 wiring: providers, pipeline placement, bypass token, console command, env-based toggling.

## Development

```bash
make install
make build
make cs-fix
make mutation
```

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
