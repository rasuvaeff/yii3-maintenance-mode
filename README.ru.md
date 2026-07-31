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

- PHP 8.3 – 8.5
- `psr/http-message` ^2.0
- `psr/http-server-middleware` ^1.0
- `psr/http-server-handler` ^1.0
- `psr/http-factory` ^1.0 и реализация PSR-17 — `MaintenanceMiddleware` принимает `ResponseFactoryInterface`

## Установка

```bash
composer require rasuvaeff/yii3-maintenance-mode
```

## Использование

### 1. Добавьте middleware в pipeline

`MaintenanceMiddleware` должен выполняться **до routing'а и аутентификации** —
иначе запросы дойдут до router'а даже во время maintenance. При этом он **не
должен** быть самым внешним middleware: глобальный error catcher, request ID и
observability-middleware обязаны оборачивать его, иначе ошибка, возникшая при
выдаче maintenance-ответа, вернётся без них.

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

В стеке middleware Yii3 первый элемент — самый внешний: всё, что перечислено
выше `MaintenanceMiddleware`, оборачивает его, а всё, что ниже, во время
maintenance не выполняется.

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

Пакет поставляет `config/di.php` и `config/params.php`. Они подключаются
автоматически **только если корневое приложение использует config-plugin
`yiisoft/config`** — так устроены `yiisoft/app` и `yiisoft/app-api`. Без плагина
не регистрируется ничего, сервисы нужно связать вручную:

```php
use Psr\Http\Message\ResponseFactoryInterface;
use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;

$middleware = new MaintenanceMiddleware(
    provider: new ConfigMaintenanceProvider($config),
    responseFactory: $container->get(ResponseFactoryInterface::class),
);
```

### 3. Включение режима maintenance

**Через переменную окружения** (без deploy):

```php
// config/params.php
'rasuvaeff/yii3-maintenance-mode' => [
    'enabled'         => filter_var($_ENV['MAINTENANCE_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
    'retryAfter'      => 600,
    'allowedIps'      => ['10.0.0.1'],
    'bypassTokenHash' => (string) ($_ENV['MAINTENANCE_BYPASS_HASH'] ?? ''),
],
```

Разбирайте флаг через `filter_var(..., FILTER_VALIDATE_BOOL)`, а не приведением
`(bool)`: переменные окружения — строки, а `(bool) 'false'`, `(bool) '0.0'` и
`(bool) 'off'` равны `true`, то есть приведение превращает «maintenance
выключен» во «включён».

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

Файл перечитывается на каждый запрос, поэтому его нельзя увидеть записанным
наполовину. Пишите временный файл **в том же каталоге** и перемещайте его на
место: `rename()` в пределах одной файловой системы атомарен, а обычный
`echo > maintenance.json` — нет.

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

Удаление файла тоже выключает maintenance: отсутствующий файл читается как
состояние по умолчанию (выключено). Явный `{"enabled":false}` лучше — он
отличает «выключили осознанно» от «файл пропал».

## Bypass-токен

Сгенерируйте длинный случайный токен и храните только его hash:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"          # сам токен
php -r "echo hash('sha256', 'my-secret-token'), PHP_EOL;"  # то, что идёт в конфиг
# ea5add57437cbf20af59034d7ed17968dcc56767b41965fcc5b376d45db8b4a3
```

Храните hash в конфиге (никогда сам токен):

```php
'bypassTokenHash' => 'ea5add57437cbf20af59034d7ed17968dcc56767b41965fcc5b376d45db8b4a3',
```

Доступ к любому URL с добавлением `?bypass=my-secret-token`:

```
https://example.com/?bypass=my-secret-token
https://example.com/admin/dashboard?bypass=my-secret-token
```

Токен сравнивается через `hash_equals()` — это убирает timing-канал утечки, но
не защищает от перебора: используйте минимум 32 случайных байта и включайте
rate-limit на веб-сервере, если maintenance-страница доступна публично.

**Query-параметр передаётся внутри URL.** Он попадает в access-логи веб-сервера,
логи приложения, заголовок `Referer` исходящих ссылок, историю браузера и на
каждый промежуточный proxy. Отдавайте сайт по TLS, считайте токен
скомпрометированным после использования на чужой машине и меняйте его после
maintenance-окна. Передачи токена через заголовок в 1.0.x нет.

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

Реализуйте его для создания кастомных провайдеров (БД, Redis, feature-flag
и т. д.). Провайдер отдаёт только состояние — на тело ответа он не влияет.

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

Формат `maintenance.json`:

```json
{
    "enabled": true,
    "retryAfter": 600,
    "allowedIps": ["10.0.0.1"],
    "bypassTokenHash": "ea5add57437cbf20af59034d7ed17968dcc56767b41965fcc5b376d45db8b4a3"
}
```

**Поведение при ошибке — fail-open.** Отсутствующий файл, нечитаемый файл и
невалидный JSON дают состояние по умолчанию (`enabled: false`), то есть трафик
продолжает идти. Это удобно (потерянный файл не заблокирует сайт) и опасно
(опечатка в deploy-скрипте молча отменяет maintenance-окно). Значения
приводятся, а не валидируются: `"retryAfter": "soon"` превращается в `0`.
Проверяйте результат после записи — `curl -sI https://example.com/ | head -1`
должен вернуть `503`.

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

Возвращается минимальная HTML-страница maintenance. В 1.0.x тело ответа не
настраивается: кастомный `MaintenanceProvider` отдаёт состояние, а не разметку.
Чтобы оформить страницу по-своему, оберните middleware собственным
`MiddlewareInterface` и перепишите тело возвращённого им ответа 503.

## Безопасность

- Bypass-токен сравнивается через `hash_equals()` — timing-канала нет. Защита от перебора — задача приложения.
- Хранится только SHA-256 hash, никогда plaintext-токен.
- Bypass-токен передаётся query-параметром и потому логируется на каждом узле. Используйте TLS и ротацию.
- IP allow-list — строгое строковое сравнение с `REMOTE_ADDR`: без CIDR, без диапазонов, без нормализации IPv6 (`::1` и `0:0:0:0:0:0:0:1` — разные строки).
- За reverse proxy, балансировщиком или CDN в `REMOTE_ADDR` лежит адрес proxy, поэтому allow-list совпадёт либо со всеми запросами, либо ни с одним. Пакет намеренно игнорирует `X-Forwarded-For`: доверие к нему без allow-list доверенных proxy позволило бы любому клиенту подделать разрешённый адрес. Применяйте allow-list на стороне proxy.
- `FileMaintenanceProvider` работает по схеме fail-open: нечитаемый или битый файл оставляет сайт доступным (см. выше).

## Ограничения 1.0.x

Не поддерживаются: bypass-токен в заголовке, исключения путей для health- и
readiness-эндпоинтов, сопоставление по CIDR, определение клиентского IP через
доверенные proxy, кастомные response factory, атомарный writer состояния и
консольная команда. Не настраивайте их — в этом релизе их нет.

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
