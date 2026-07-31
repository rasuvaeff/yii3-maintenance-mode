<?php

declare(strict_types=1);

/**
 * How to wire yii3-maintenance-mode into a Yii3 application.
 *
 * Sections:
 *   1. params.php — настройка через конфиг (подходит большинству)
 *   2. params.php — настройка через файл (runtime toggle без деплоя)
 *   3. DI config — middleware + провайдер
 *   4. Middleware pipeline — добавить в app
 *   5. Bypass token — как получить и использовать
 *   6. Консольная команда — включить/выключить без правки конфигов
 *   7. JSON responses
 *
 * This file does not run standalone — copy the relevant parts into your app.
 */


// =========================================================================
// 1. Config-based provider (params.php)
//    Значения берутся из конфига, меняются через деплой или env.
// =========================================================================
//
// config/params.php
//
// return [
//     'rasuvaeff/yii3-maintenance-mode' => [
//         'enabled'         => filter_var($_ENV['MAINTENANCE_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
//         'retryAfter'      => 600,                  // секунд до следующей попытки
//         'allowedIps'      => ['10.0.0.1'],         // IP разработчиков (exact match, no CIDR)
//         'bypassTokenHash' => (string) ($_ENV['MAINTENANCE_BYPASS_HASH'] ?? ''),
//     ],
// ];
//
// Флаг разбирать через filter_var(..., FILTER_VALIDATE_BOOL), а не приведением
// (bool): env-переменные — строки, и (bool) 'false' === true.
//
// Включить через .env:
//   MAINTENANCE_ENABLED=true
//   MAINTENANCE_BYPASS_HASH=<sha256 от вашего токена>
//
// config/di.php и config/params.php пакета подхватываются автоматически только
// если корневое приложение использует config-plugin yiisoft/config
// (yiisoft/app, yiisoft/app-api). Иначе сервисы связываются вручную —
// см. секцию 3.


// =========================================================================
// 2. File-based provider (runtime toggle)
//    Удобно когда нужно включить/выключить без деплоя.
// =========================================================================
//
// Файл /var/www/app/maintenance.json:
//
// {
//     "enabled": true,
//     "retryAfter": 600,
//     "allowedIps": ["10.0.0.1"],
//     "bypassTokenHash": "ea5add57437cbf20af59034d7ed17968dcc56767b41965fcc5b376d45db8b4a3"
// }
//
// Файл перечитывается на каждый запрос, поэтому писать его нужно атомарно:
// временный файл в том же каталоге + mv. Обычный `echo > maintenance.json`
// оставляет окно, в котором запрос прочитает наполовину записанный JSON и
// получит fail-open (сайт открыт).
//
// Включить maintenance:
//   dir=/var/www/app
//   tmp="$(mktemp "$dir/maintenance.json.XXXXXX")"
//   printf '%s' '{"enabled":true,"retryAfter":600}' > "$tmp"
//   chmod 0644 "$tmp"
//   mv -f "$tmp" "$dir/maintenance.json"
//
// Выключить maintenance (явное состояние лучше удаления файла):
//   tmp="$(mktemp "$dir/maintenance.json.XXXXXX")"
//   printf '%s' '{"enabled":false}' > "$tmp"
//   chmod 0644 "$tmp"
//   mv -f "$tmp" "$dir/maintenance.json"
//
// Отсутствующий, нечитаемый или битый файл тоже даёт disabled-state: провайдер
// работает по схеме fail-open. Ошибка в deploy-скрипте молча отменит
// maintenance-окно, поэтому результат надо проверять:
//   curl -sI https://example.com/ | head -1   # => HTTP/1.1 503


// =========================================================================
// 3. DI config
// =========================================================================
//
// Вариант А — ConfigMaintenanceProvider (из params.php):
//
// use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
// use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;
// use Rasuvaeff\Yii3MaintenanceMode\MaintenanceProvider;
//
// return [
//     MaintenanceProvider::class => [
//         '__construct()' => [
//             'config' => $params['rasuvaeff/yii3-maintenance-mode'],
//         ],
//     ],
//     MaintenanceMiddleware::class => MaintenanceMiddleware::class,
// ];
//
//
// Вариант Б — FileMaintenanceProvider (runtime файл):
//
// use Rasuvaeff\Yii3MaintenanceMode\FileMaintenanceProvider;
// use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;
// use Rasuvaeff\Yii3MaintenanceMode\MaintenanceProvider;
//
// return [
//     MaintenanceProvider::class => [
//         'class' => FileMaintenanceProvider::class,
//         '__construct()' => [
//             'filePath' => dirname(__DIR__) . '/maintenance.json',
//         ],
//     ],
//     MaintenanceMiddleware::class => MaintenanceMiddleware::class,
// ];


// =========================================================================
// 4. Middleware pipeline
//    До роутинга и аутентификации, но внутри ErrorCatcher.
// =========================================================================
//
// config/common/di.php или config/web.php:
//
// use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;
// use Yiisoft\Yii\Http\Application;
//
// return [
//     Application::class => [
//         '__construct()' => [
//             'dispatcher' => DynamicMiddlewareDispatcher::class,
//             'middlewares' => [
//                 ErrorCatcher::class,            // ← самый внешний
//                 // request ID, логирование и трассировка, security-заголовки …
//                 MaintenanceMiddleware::class,   // ← до роутинга и аутентификации
//                 Router::class,
//             ],
//         ],
//     ],
// ];
//
// Первый элемент списка — самый внешний middleware. Ставить
// MaintenanceMiddleware перед ErrorCatcher нельзя: ошибка, возникшая при выдаче
// 503, вернётся без обработки, без request ID и без записи в трассировку.


// =========================================================================
// 5. Bypass token — как сгенерировать и использовать
// =========================================================================
//
// Сгенерировать сам токен (минимум 32 случайных байта):
//   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
//
// Сгенерировать хэш токена:
//   php -r "echo hash('sha256', 'my-secret-token');"
//   # => ea5add57437cbf20af59034d7ed17968dcc56767b41965fcc5b376d45db8b4a3
//
// Записать в .env:
//   MAINTENANCE_BYPASS_HASH=ea5add57...
//
// Или в maintenance.json:
//   {"enabled":true,"bypassTokenHash":"ea5add57..."}
//
// Использовать в браузере (добавить ?bypass= к любому URL):
//   https://example.com/?bypass=my-secret-token
//   https://example.com/admin?bypass=my-secret-token
//
// Middleware сравнивает hash('sha256', $token) с сохранённым хэшем через
// hash_equals() — это убирает timing-канал, но не защищает от перебора.
//
// Токен передаётся в URL, поэтому попадает в access-логи, заголовок Referer,
// историю браузера и на промежуточные proxy. Только TLS, и ротация токена
// после maintenance-окна. Передачи через заголовок в 1.0.x нет.


// =========================================================================
// 6. Консольная команда для toggling без правки конфигов
//    Пример для FileMaintenanceProvider.
//    Разместить в src/Console/MaintenanceCommand.php.
// =========================================================================
//
// final class MaintenanceCommand
// {
//     private string $filePath;
//
//     public function __construct(string $storageDir)
//     {
//         $this->filePath = $storageDir . '/maintenance.json';
//     }
//
//     public function enable(int $retryAfter = 600): void
//     {
//         $this->write([
//             'enabled'         => true,
//             'retryAfter'      => $retryAfter,
//             'allowedIps'      => [],
//             'bypassTokenHash' => '',
//         ]);
//
//         echo "Maintenance mode enabled.\n";
//     }
//
//     public function disable(): void
//     {
//         $this->write(['enabled' => false]);
//
//         echo "Maintenance mode disabled.\n";
//     }
//
//     public function status(): void
//     {
//         $state = (new FileMaintenanceProvider($this->filePath))->getState();
//
//         echo 'Status: ' . ($state->enabled ? 'ENABLED' : 'disabled') . "\n";
//     }
//
//     /** @param array<string, mixed> $state */
//     private function write(array $state): void
//     {
//         // Временный файл в том же каталоге: rename() в пределах одной
//         // файловой системы атомарен, и запрос никогда не прочитает
//         // наполовину записанный JSON.
//         $tmp = $this->filePath . '.' . bin2hex(random_bytes(6));
//
//         file_put_contents($tmp, json_encode($state, JSON_THROW_ON_ERROR));
//         chmod($tmp, 0o644);
//         rename($tmp, $this->filePath);
//     }
// }
//
// Использование с Yii3 console:
//   php yii maintenance/enable
//   php yii maintenance/disable
//   php yii maintenance/status
//
// status читает состояние через провайдер, а не проверяет существование файла:
// файл может лежать на месте с {"enabled":false}.


// =========================================================================
// 7. JSON responses
// =========================================================================
//
// Обычный браузерный запрос (Accept: text/html)  →  503 HTML
//
//   HTTP/1.1 503 Service Unavailable
//   Retry-After: 600
//   Content-Type: text/html; charset=utf-8
//
//   <!DOCTYPE html>...
//
//
// API-запрос (Accept: application/json или без Accept)  →  503 JSON
//
//   HTTP/1.1 503 Service Unavailable
//   Retry-After: 600
//   Content-Type: application/json
//
//   {
//       "error": "Service Unavailable",
//       "message": "The server is currently undergoing maintenance.",
//       "retryAfter": 600
//   }
//
// Тело ответа в 1.0.x не настраивается: провайдер отдаёт состояние, а не
// разметку. Чтобы оформить страницу, оберните MaintenanceMiddleware
// собственным MiddlewareInterface и перепишите тело ответа 503.
//
//
// IP из allowedIps или валидный bypass token  →  200 (запрос проходит)
//
// allowedIps сравнивается строго со строкой REMOTE_ADDR: без CIDR, без
// диапазонов. За reverse proxy в REMOTE_ADDR лежит адрес proxy — allow-list
// совпадёт либо со всеми, либо ни с одним. X-Forwarded-For пакет намеренно не
// читает; ограничивайте доступ на стороне proxy.
