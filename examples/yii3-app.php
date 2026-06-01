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
//         'enabled'         => (bool) ($_ENV['MAINTENANCE_ENABLED'] ?? false),
//         'retryAfter'      => 600,                  // секунд до следующей попытки
//         'allowedIps'      => ['10.0.0.1'],         // IP разработчиков (exact match, no CIDR)
//         'bypassTokenHash' => $_ENV['MAINTENANCE_BYPASS_HASH'] ?? '',
//     ],
// ];
//
// Включить через .env:
//   MAINTENANCE_ENABLED=true
//   MAINTENANCE_BYPASS_HASH=<sha256 от вашего токена>


// =========================================================================
// 2. File-based provider (runtime toggle)
//    Удобно когда нужно включить/выключить без деплоя.
//    Достаточно создать/удалить JSON-файл на сервере или через Deployer.
// =========================================================================
//
// Файл /var/www/app/maintenance.json:
//
// {
//     "enabled": true,
//     "retryAfter": 600,
//     "allowedIps": ["10.0.0.1"],
//     "bypassTokenHash": "9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08"
// }
//
// Включить maintenance:
//   echo '{"enabled":true,"retryAfter":600}' > /var/www/app/maintenance.json
//
// Выключить maintenance:
//   rm /var/www/app/maintenance.json


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
//    Добавить как можно раньше — до роутинга, до аутентификации.
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
//                 MaintenanceMiddleware::class,   // ← первым в цепочке
//                 ErrorCatcher::class,
//                 Router::class,
//             ],
//         ],
//     ],
// ];
//
// Или в конфиге middlewares-а Yii3 HTTP runner:
//
// return [
//     MaintenanceMiddleware::class,
//     // ... остальные middleware
// ];


// =========================================================================
// 5. Bypass token — как сгенерировать и использовать
// =========================================================================
//
// Сгенерировать хэш токена:
//   php -r "echo hash('sha256', 'my-secret-token');"
//   # => 9f86d081...
//
// Записать в .env:
//   MAINTENANCE_BYPASS_HASH=9f86d081...
//
// Или в maintenance.json:
//   {"enabled":true,"bypassTokenHash":"9f86d081..."}
//
// Использовать в браузере (добавить ?bypass= к любому URL):
//   https://example.com/?bypass=my-secret-token
//   https://example.com/admin?bypass=my-secret-token
//
// Middleware сравнивает hash('sha256', $token) с сохранённым хэшем через
// hash_equals() — защита от timing attacks.


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
//         file_put_contents($this->filePath, json_encode([
//             'enabled'         => true,
//             'retryAfter'      => $retryAfter,
//             'allowedIps'      => [],
//             'bypassTokenHash' => '',
//         ], JSON_PRETTY_PRINT));
//
//         echo "Maintenance mode enabled.\n";
//     }
//
//     public function disable(): void
//     {
//         if (file_exists($this->filePath)) {
//             unlink($this->filePath);
//         }
//         echo "Maintenance mode disabled.\n";
//     }
//
//     public function status(): void
//     {
//         $active = file_exists($this->filePath);
//         echo 'Status: ' . ($active ? 'ENABLED' : 'disabled') . "\n";
//     }
// }
//
// Использование с Yii3 console:
//   php yii maintenance/enable
//   php yii maintenance/disable
//   php yii maintenance/status


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
//
// IP из allowedIps или валидный bypass token  →  200 (запрос проходит)
