# rasuvaeff/yii3-maintenance-mode
[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-maintenance-mode.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-maintenance-mode.svg)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-maintenance-mode/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-maintenance-mode/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![Psalm Level](https://img.shields.io/badge/Psalm-1-blue.svg)](https://github.com/rasuvaeff/yii3-maintenance-mode/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-maintenance-mode/php)](https://packagist.org/packages/rasuvaeff/yii3-maintenance-mode)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-maintenance-mode.svg)](LICENSE.md)
Промежуточное программное обеспечение режима обслуживания PSR-15 для Yii3. Возвращает HTTP 503 с заголовком Retry-After. Поддерживает список разрешенных IP-адресов, токен обхода, ответы JSON и HTML.

 > Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) содержит компактную ссылку на API, готовую для вставки в контекст. @@ЛИНИЯ@@
## Требования
- PHP 8.3+
 - `psr/http-message` ^2.0
 - `psr/http-server-middleware` ^1.0

## Установка
```bash
composer require rasuvaeff/yii3-maintenance-mode
```
## Использование
### 1. Добавьте промежуточное ПО в конвейер
`MaintenanceMiddleware` должен быть установлен **как можно раньше** — до маршрутизации и аутентификации. В противном случае запросы дойдут до маршрутизатора даже во время обслуживания. @@ЛИНИЯ@@
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
### 2. Настройте в params.php
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
Пакет поставляется с `config/di.php` и `config/params.php` через config-plugin. ConfigMaintenanceProvider и MaintenanceMiddleware регистрируются автоматически. @@ЛИНИЯ@@
### 3. Включить режим обслуживания.
**Через переменную среды** (развертывание не требуется):

```php
// config/params.php
'rasuvaeff/yii3-maintenance-mode' => [
    'enabled'         => (bool) ($_ENV['MAINTENANCE_ENABLED'] ?? false),
    'retryAfter'      => 600,
    'allowedIps'      => ['10.0.0.1'],
    'bypassTokenHash' => $_ENV['MAINTENANCE_BYPASS_HASH'] ?? '',
],
```
**Через файл JSON** (переключение без развертывания или перезапуска):

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
Включить: `echo '{"enabled":true, "retryAfter":600}' > Maintenance.json`
 Отключить: `rm Maintenance.json`

## Обход токена
Сгенерируйте хэш вашего секретного токена:

```bash
php -r "echo hash('sha256', 'my-secret-token');"
# 9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08
```
Сохраните хеш в конфигурации (никогда сам токен):

```php
'bypassTokenHash' => '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08',
```
Получите доступ к любому URL-адресу, добавив `?bypass=my-secret-token`:

```
https://example.com/?bypass=my-secret-token
https://example.com/admin/dashboard?bypass=my-secret-token
```
Токен сравнивается с hash_equals() — безопасно по времени, без риска перебора. @@ЛИНИЯ@@
## Справочник по API
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
Реализуйте это для создания пользовательских поставщиков (БД, Redis, флаг функции и т. д.). @@ЛИНИЯ@@
### `ConfigMaintenanceProvider`
```php
$provider = new ConfigMaintenanceProvider([
    'enabled'         => true,
    'retryAfter'      => 600,
    'allowedIps'      => ['127.0.0.1', '10.0.0.1'],
    'bypassTokenHash' => hash('sha256', 'secret'),
]);
```
Состояние неизменяемо — устанавливается один раз при создании. Лучше всего подходит для источников конфигурации/окружения. @@ЛИНИЯ@@
### `FileMaintenanceProvider`
```php
$provider = new FileMaintenanceProvider(filePath: '/var/app/maintenance.json');
```
Считывает состояние при каждом вызове getState() — изменения вступают в силу без перезапуска.
 Возвращает отключенное состояние («включено: false»), когда файл отсутствует или неверен JSON.

 формат `maintenance.json`:

```json
{
    "enabled": true,
    "retryAfter": 600,
    "allowedIps": ["10.0.0.1"],
    "bypassTokenHash": "9f86d081..."
}
```
### `Промежуточное ПО для обслуживания`
```php
$middleware = new MaintenanceMiddleware(
    provider: $provider,
    responseFactory: $responseFactory,
);
```
Логика принятия решения (по порядку):

 | Состояние | Действие |
 |---|---|
 | `включено === false` | Пройти |
 | `REMOTE_ADDR` в `allowedIps` | Пройти |
 | Действительный токен `?bypass=` | Пройти |
 | В противном случае | Вернуться 503 | @@ЛИНИЯ@@
## Формат ответа
### JSON (клиенты API)
Возвращается, когда заголовок Accept: application/json или Accept отсутствует:

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
Возвращается, когда `Accept: text/html` или любой другой код, отличный от JSON, принимает:

```http
HTTP/1.1 503 Service Unavailable
Content-Type: text/html; charset=utf-8
Retry-After: 600
```
Возвращается минимальная страница обслуживания HTML. Переопределите, реализовав собственную оболочку MaintenanceProvider или декоратор MiddlewareInterface. @@ЛИНИЯ@@
## Безопасность
- Обход токена по сравнению с `hash_equals()` — безопасен по времени
 - Сохраняется только хэш, а не открытый текстовый токен
 - Список разрешенных IP-адресов использует строгое сравнение строк на `REMOTE_ADDR`
 - `FileMaintenanceProvider` корректно перехватывает `JsonException` — недопустимый файл → отключенное состояние (безопасное значение по умолчанию)

## Примеры
См. [`examples/`](examples/) для получения подробной информации о подключении Yii3: провайдеры, размещение конвейера, обходной токен, консольные команды, переключение на основе env. @@ЛИНИЯ@@
## Разработка
```bash
make install
make build
make cs-fix
make mutation
```
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
