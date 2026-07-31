# rasuvaeff/yii3-maintenance-mode

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-maintenance-mode.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-maintenance-mode.svg)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-maintenance-mode/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-maintenance-mode/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![Psalm Level](https://img.shields.io/badge/Psalm-1-blue.svg)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-maintenance-mode/php)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-maintenance-mode.svg)](LICENSE.md)
[Русская версия](README.ru.md)

Production-oriented PSR-15 maintenance middleware for Yii3. It returns HTTP
503 with `Retry-After`, supports safe header bypass, health-path exclusions,
IPv4/IPv6 CIDR allow-lists, trusted proxies, atomic file switching, and custom
responses.

> Using an AI coding assistant? [llms.txt](llms.txt) contains a compact API
> reference ready to paste into context.

## Requirements

- PHP 8.3-8.5
- ext-filter
- PSR-7 HTTP messages and PSR-17 response factory
- PSR-15 middleware and request handler
- Yii config-plugin in the root application for automatic Yii3 DI registration

## Installation

```bash
composer require rasuvaeff/yii3-maintenance-mode
```

## Usage

### Middleware order

Place maintenance mode before routing and authentication, but inside global
error handling, request ID, observability, CORS, and security middleware. This
keeps 503 responses observable and ensures middleware failures are caught.

```php
return [
    ErrorCatcher::class,
    RequestIdMiddleware::class,
    SecurityHeadersMiddleware::class,
    MaintenanceMiddleware::class,
    Router::class,
    Authentication::class,
];
```

### Configuration provider

The package registers `ConfigMaintenanceProvider` and
`MaintenanceMiddleware` through Yii config-plugin.

```php
// config/params.php
$enabled = filter_var(
    $_ENV['MAINTENANCE_ENABLED'] ?? 'false',
    FILTER_VALIDATE_BOOL,
    FILTER_NULL_ON_FAILURE,
) ?? false;

return [
    'rasuvaeff/yii3-maintenance-mode' => [
        'enabled' => $enabled,
        'retryAfter' => 300,
        'allowedIps' => [],
        'bypassTokenHash' => $_ENV['MAINTENANCE_BYPASS_HASH'] ?? '',
    ],
];
```

`ConfigMaintenanceProvider` captures immutable state when the container creates
it. Use `FileMaintenanceProvider` when state must change without rebuilding the
container.

`config/di.php` and `config/params.php` are merged automatically **only when
the root application uses the `yiisoft/config` plugin** (`yiisoft/app`,
`yiisoft/app-api`). Without it, wire the services by hand:

```php
use Psr\Http\Message\ResponseFactoryInterface;
use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;

$middleware = new MaintenanceMiddleware(
    provider: new ConfigMaintenanceProvider($config),
    responseFactory: $container->get(ResponseFactoryInterface::class),
);
```

### Atomic file switching

Use the installed command instead of shell redirection or deleting the file.
It writes a complete temporary file and atomically renames it.

```bash
vendor/bin/yii3-maintenance enable --file=/var/app/maintenance.json --retry-after=600
vendor/bin/yii3-maintenance status --file=/var/app/maintenance.json
vendor/bin/yii3-maintenance disable --file=/var/app/maintenance.json
```

Bind the file provider in application DI:

```php
use Rasuvaeff\Yii3MaintenanceMode\FileMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceProvider;

return [
    MaintenanceProvider::class => [
        'class' => FileMaintenanceProvider::class,
        '__construct()' => [
            'filePath' => '/var/app/maintenance.json',
        ],
    ],
];
```

`FileMaintenanceProvider` accepts strict JSON types. Missing, unreadable,
malformed, or schema-invalid files currently produce disabled state. This is a
legacy fail-open policy; use the atomic writer and monitor file errors rather
than relying on partial manual writes.

## Bypass token

Generate a high-entropy token and its SHA-256 hash:

```bash
php -r '$token = bin2hex(random_bytes(32)); echo "token=$token\nhash=" . hash("sha256", $token) . "\n";'
```

For the literal token `my-secret-token`, the correct hash is:

```text
ea5add57437cbf20af59034d7ed17968dcc56767b41965fcc5b376d45db8b4a3
```

Store only the hash and send the plaintext token over TLS in a header:

```bash
curl -H 'X-Maintenance-Bypass: <plaintext-token>' https://example.com/admin
```

The comparison uses `hash_equals()`. This protects against timing attacks, not
weak-token brute force. The legacy `?bypass=` query parameter remains enabled
for backward compatibility, but URLs may leak through logs, browser history,
analytics, and referrers. Disable it in new deployments.

```php
use Rasuvaeff\Yii3MaintenanceMode\DefaultMaintenanceAccessPolicy;

$policy = new DefaultMaintenanceAccessPolicy(
    allowQueryParameter: false,
);
```

## Health paths, CIDR, and proxies

`allowedIps` accepts exact IPv4/IPv6 addresses and CIDR rules. The default IP
resolver only accepts a valid `REMOTE_ADDR`.

```php
$state = new MaintenanceState(
    enabled: true,
    allowedIps: ['10.0.0.0/8', '2001:db8::/32'],
);
```

Exclude health endpoints and resolve forwarded client addresses only from
explicitly trusted proxies:

```php
use Rasuvaeff\Yii3MaintenanceMode\DefaultMaintenanceAccessPolicy;
use Rasuvaeff\Yii3MaintenanceMode\TrustedProxyClientIpResolver;

$policy = new DefaultMaintenanceAccessPolicy(
    clientIpResolver: new TrustedProxyClientIpResolver(
        trustedProxies: ['10.0.0.0/8'],
    ),
    excludedPathPrefixes: ['/health', '/ready', '/metrics'],
    allowQueryParameter: false,
);
```

Never trust `X-Forwarded-For` without a trusted-proxy allow-list.

## Custom response

Implement `MaintenanceResponseFactory` and inject it into
`MaintenanceMiddleware` to provide branded HTML, RFC 9457 problem details,
localization, or additional headers.

```php
$middleware = new MaintenanceMiddleware(
    provider: $provider,
    responseFactory: $psr17ResponseFactory,
    accessPolicy: $policy,
    maintenanceResponseFactory: $customMaintenanceResponseFactory,
);
```

The default factory returns:

- HTTP 503;
- `Retry-After`;
- `Cache-Control: no-store`;
- JSON for absent `Accept`, `application/json`, and `application/*+json`;
- HTML when `text/html` has the higher quality value.

## API reference

| API | Purpose |
|---|---|
| `MaintenanceMiddleware` | PSR-15 maintenance decision and response |
| `MaintenanceState` | Immutable enabled/retry/IP/hash state |
| `MaintenanceProvider` | State provider contract |
| `ConfigMaintenanceProvider` | Immutable array/config-backed provider |
| `FileMaintenanceProvider` | Strict JSON provider read on each request |
| `AtomicMaintenanceFileWriter` | Atomic file-state replacement |
| `MaintenanceAccessPolicy` | Request bypass contract |
| `DefaultMaintenanceAccessPolicy` | Header/query token, IP/CIDR, path policy |
| `ClientIpResolver` | Client IP resolution contract |
| `RemoteAddrClientIpResolver` | Validated direct peer address |
| `TrustedProxyClientIpResolver` | Trusted forwarded-address chain |
| `MaintenanceResponseFactory` | Custom 503 response contract |
| `DefaultMaintenanceResponseFactory` | Negotiated JSON/HTML response |

## Security

- Keep bypass tokens random, secret, and TLS-only.
- Prefer `X-Maintenance-Bypass`; disable query bypass in new deployments.
- Never log the plaintext bypass header.
- Configure trusted proxies explicitly before reading forwarded headers.
- Keep health exclusions narrow and unauthenticated-data-free.
- Use the atomic writer; do not update JSON with `echo > file`.
- Invalid file state is fail-open for backward compatibility and should be
  monitored.

## Examples

See [`examples/`](examples/) for executable config and file-provider examples.

## Development

```bash
make build
make test-coverage
make mutation
make release-check
```

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
