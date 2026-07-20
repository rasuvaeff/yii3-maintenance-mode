# rasuvaeff/yii3-maintenance-mode

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-maintenance-mode.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-maintenance-mode.svg)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-maintenance-mode/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-maintenance-mode/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![Psalm Level](https://img.shields.io/badge/Psalm-1-blue.svg)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-maintenance-mode/php)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-maintenance-mode.svg)](LICENSE.md)
[English version](README.md)

PSR-15 middleware режима maintenance для Yii3. Возвращает HTTP 503 с заголовком
`Retry-After`. Поддерживает IP allow-list, bypass-токен, JSON- и HTML-ответы.

> Используете AI-ассистента для написания кода? В [llms.txt](llms.txt) — компактный
> API-справочник, готовый для вставки в контекст.

## Требования

- PHP 8.3+
- `psr/http-message` ^2.0
- `psr/http-server-middleware` ^1.0

## Установка

```bash
composer require rasuvaeff/yii3-maintenance-mode
```

## Использование

### 1. Добавьте middleware в pipeline

`MaintenanceMiddleware` должен быть размещён **как можно раньше** — до routing'а
и аутентификации. Иначе запросы дойдут до router'а даже во время maintenance.

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

### 2. Сконфигурируйте в params.php

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

Пакет поставляет `config/di.php` и `config/params.php` через config-plugin.
`ConfigMaintenanceProvider` и `MaintenanceMiddleware` регистрируются
автоматически.

### 3. Включение режима maintenance

**Через переменную окружения** (без deploy):

```php
// config/params.php
'rasuvaeff/yii3-maintenance-mode' => [
    'enabled'         => (bool) ($_ENV['MAINTENANCE_ENABLED'] ?? false),
    'retryAfter'      => 600,
    'allowedIps'      => ['10.0.0.1'],
    'bypassTokenHash' => $_ENV['MAINTENANCE_BYPASS_HASH'] ?? '',
],
```

**Через JSON-файл** (переключение без deploy или restart):

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

Включить: `echo '{"enabled":true,"retryAfter":600}' > maintenance.json`  
Выключить: `rm maintenance.json`

## Bypass-токен

Сгенерируйте hash вашего секретного токена:

```bash
php -r "echo hash('sha256', 'my-secret-token');"
# 9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08
```

Храните hash в конфиге (никогда сам токен):

```php
'bypassTokenHash' => '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08',
```

Доступ к любому URL с добавлением `?bypass=my-secret-token`:

```
https://example.com/?bypass=my-secret-token
https://example.com/admin/dashboard?bypass=my-secret-token
```

Токен сравнивается через `hash_equals()` — timing-safe, без риска brute-force.

## Справочник API

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

### `MaintenanceProvider` (интерфейс)

```php
interface MaintenanceProvider {
    public function getState(): MaintenanceState;
}
```

Реализуйте его для создания кастомных провайдеров (БД, Redis, feature-flag и т. д.).

### `ConfigMaintenanceProvider`

```php
$provider = new ConfigMaintenanceProvider([
    'enabled'         => true,
    'retryAfter'      => 600,
    'allowedIps'      => ['127.0.0.1', '10.0.0.1'],
    'bypassTokenHash' => hash('sha256', 'secret'),
]);
```

State иммутабелен — задаётся один раз при конструировании. Лучше всего подходит
для config/env-источников.

### `FileMaintenanceProvider`

```php
$provider = new FileMaintenanceProvider(filePath: '/var/app/maintenance.json');
```

Читает state на каждый вызов `getState()` — изменения применяются без restart'а.
Возвращает disabled-state (`enabled: false`), когда файл отсутствует или содержит
невалидный JSON.

Формат `maintenance.json`:

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

Логика решения (по порядку):

| Условие | Действие |
|---|---|
| `enabled === false` | Пропустить |
| `REMOTE_ADDR` в `allowedIps` | Пропустить |
| Валидный `?bypass=` токен | Пропустить |
| Иначе | Вернуть 503 |

## Формат ответа

### JSON (API-клиенты)

Возвращается при `Accept: application/json` или отсутствии заголовка `Accept`:

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

### HTML (браузеры)

Возвращается при `Accept: text/html` или любом другом не-JSON accept:

```http
HTTP/1.1 503 Service Unavailable
Content-Type: text/html; charset=utf-8
Retry-After: 600
```

Возвращается минимальная HTML-страница maintenance. Переопределите, реализовав
собственную обёртку `MaintenanceProvider` или декоратор `MiddlewareInterface`.

## Безопасность

- Bypass-токен сравнивается через `hash_equals()` — timing-safe.
- Хранится только hash, никогда plaintext-токен.
- IP allow-list использует строгое строковое сравнение на `REMOTE_ADDR`.
- `FileMaintenanceProvider` аккуратно ловит `JsonException` — невалидный файл →
  disabled-state (безопасное значение по умолчанию).

## Примеры

См. [`examples/`](examples/) — детальное Yii3-wiring: провайдеры, размещение в
pipeline, bypass-токен, console-команда, переключение через env.

## Разработка

```bash
make install
make build
make cs-fix
make mutation
```

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
