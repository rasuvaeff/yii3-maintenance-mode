# rasuvaeff/yii3-maintenance-mode

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-maintenance-mode.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-maintenance-mode.svg)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-maintenance-mode/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-maintenance-mode/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![Psalm Level](https://img.shields.io/badge/Psalm-1-blue.svg)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-maintenance-mode/php)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-maintenance-mode.svg)](LICENSE.md)
[English version](README.md)

Ориентированный на production PSR-15 middleware режима maintenance для Yii3.
Он возвращает HTTP 503 с `Retry-After`, поддерживает безопасный bypass через
header, исключения для health endpoints, IPv4/IPv6 CIDR allow-list, trusted
proxies, атомарное переключение через файл и кастомные ответы.

> Используете AI-ассистента? В [llms.txt](llms.txt) находится компактный
> API-справочник, готовый для добавления в контекст.

## Требования

- PHP 8.3-8.5
- ext-filter
- PSR-7 HTTP messages и PSR-17 response factory
- PSR-15 middleware и request handler
- Yii config-plugin в root-приложении для автоматической Yii3 DI-регистрации

## Установка

```bash
composer require rasuvaeff/yii3-maintenance-mode
```

## Использование

### Порядок middleware

Размещайте maintenance mode до routing и authentication, но внутри глобальных
error handling, request ID, observability, CORS и security middleware. Так
ответы 503 остаются наблюдаемыми, а ошибки middleware перехватываются.

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

### Config provider

Пакет регистрирует `ConfigMaintenanceProvider` и `MaintenanceMiddleware` через
Yii config-plugin.

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

`ConfigMaintenanceProvider` сохраняет immutable state при создании container.
Если state должен меняться без пересборки container, используйте
`FileMaintenanceProvider`.

`config/di.php` и `config/params.php` подключаются автоматически **только
если root-приложение использует плагин `yiisoft/config`** (`yiisoft/app`,
`yiisoft/app-api`). Без него сервисы нужно связать руками:

```php
use Psr\Http\Message\ResponseFactoryInterface;
use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;

$middleware = new MaintenanceMiddleware(
    provider: new ConfigMaintenanceProvider($config),
    responseFactory: $container->get(ResponseFactoryInterface::class),
);
```

### Атомарное переключение через файл

Используйте установленную команду вместо shell redirection или удаления файла.
Она записывает полный временный файл и атомарно переименовывает его.

```bash
vendor/bin/yii3-maintenance enable --file=/var/app/maintenance.json --retry-after=600
vendor/bin/yii3-maintenance status --file=/var/app/maintenance.json
vendor/bin/yii3-maintenance disable --file=/var/app/maintenance.json
```

Привяжите file provider в DI приложения:

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

`FileMaintenanceProvider` принимает строгие JSON-типы. Отсутствующий,
недоступный, поврежденный или не соответствующий схеме файл пока возвращает
disabled state. Это legacy fail-open policy; используйте atomic writer и
мониторинг ошибок файла вместо частичных ручных записей.

## Bypass-токен

Сгенерируйте высокоэнтропийный токен и его SHA-256 hash:

```bash
php -r '$token = bin2hex(random_bytes(32)); echo "token=$token\nhash=" . hash("sha256", $token) . "\n";'
```

Для буквального токена `my-secret-token` правильный hash:

```text
ea5add57437cbf20af59034d7ed17968dcc56767b41965fcc5b376d45db8b4a3
```

Храните только hash, а plaintext-токен передавайте по TLS в header:

```bash
curl -H 'X-Maintenance-Bypass: <plaintext-token>' https://example.com/admin
```

Сравнение использует `hash_equals()`. Оно защищает от timing attacks, но не от
brute force слабого токена. Legacy query-параметр `?bypass=` пока включен для
обратной совместимости, однако URL может попасть в логи, историю браузера,
аналитику и referrers. В новых deployments отключайте его.

```php
use Rasuvaeff\Yii3MaintenanceMode\DefaultMaintenanceAccessPolicy;

$policy = new DefaultMaintenanceAccessPolicy(
    allowQueryParameter: false,
);
```

## Health paths, CIDR и proxies

`allowedIps` принимает точные IPv4/IPv6 адреса и CIDR rules. Default IP resolver
принимает только валидный `REMOTE_ADDR`.

```php
$state = new MaintenanceState(
    enabled: true,
    allowedIps: ['10.0.0.0/8', '2001:db8::/32'],
);
```

Исключите health endpoints и разрешайте forwarded client addresses только от
явно доверенных proxies:

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

Никогда не доверяйте `X-Forwarded-For` без trusted-proxy allow-list.

## Кастомный ответ

Реализуйте `MaintenanceResponseFactory` и передайте его в
`MaintenanceMiddleware`, чтобы вернуть branded HTML, RFC 9457 problem details,
локализацию или дополнительные headers.

```php
$middleware = new MaintenanceMiddleware(
    provider: $provider,
    responseFactory: $psr17ResponseFactory,
    accessPolicy: $policy,
    maintenanceResponseFactory: $customMaintenanceResponseFactory,
);
```

Default factory возвращает:

- HTTP 503;
- `Retry-After`;
- `Cache-Control: no-store`;
- JSON при отсутствующем `Accept`, `application/json` и `application/*+json`;
- HTML, когда у `text/html` выше quality value.

## Справочник API

| API | Назначение |
|---|---|
| `MaintenanceMiddleware` | Решение о maintenance и PSR-15 response |
| `MaintenanceState` | Immutable enabled/retry/IP/hash state |
| `MaintenanceProvider` | Контракт provider состояния |
| `ConfigMaintenanceProvider` | Immutable provider из array/config |
| `FileMaintenanceProvider` | Строгий JSON provider с чтением на каждый request |
| `AtomicMaintenanceFileWriter` | Атомарная замена file state |
| `MaintenanceAccessPolicy` | Контракт bypass для request |
| `DefaultMaintenanceAccessPolicy` | Header/query token, IP/CIDR и path policy |
| `ClientIpResolver` | Контракт определения client IP |
| `RemoteAddrClientIpResolver` | Валидированный адрес непосредственного peer |
| `TrustedProxyClientIpResolver` | Доверенная цепочка forwarded addresses |
| `MaintenanceResponseFactory` | Контракт кастомного ответа 503 |
| `DefaultMaintenanceResponseFactory` | Negotiated JSON/HTML response |

## Безопасность

- Используйте случайные секретные bypass-токены только через TLS.
- Предпочитайте `X-Maintenance-Bypass`; отключайте query bypass в новых systems.
- Не логируйте plaintext bypass header.
- Явно настройте trusted proxies до чтения forwarded headers.
- Делайте health exclusions узкими и не возвращающими пользовательские данные.
- Используйте atomic writer; не обновляйте JSON через `echo > file`.
- Invalid file state пока означает fail-open и должен отслеживаться мониторингом.

## Примеры

В [`examples/`](examples/) находятся исполняемые примеры config и file provider.

## Разработка

```bash
make build
make test-coverage
make mutation
make release-check
```

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
