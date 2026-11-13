# Examples

| Script | Shows | Needs server? |
|---|---|---|
| `basic.php` | Immutable config-backed state | No |
| `file-toggle.php` | Atomic file enable/disable and strict reading | No |
| `yii3-integration.php` | Resolving `MaintenanceMiddleware` from a real Yiisoft DI container built from the package's own `config/di.php`/`config/params.php`, then running a PSR-15 request through it | No |
| `redis.php` | Redis-backed state shared across nodes (`RedisMaintenanceProvider`/`RedisMaintenanceWriter` over predis) | Redis (`REDIS_HOST`) |

## Running

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/basic.php
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/file-toggle.php
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/yii3-integration.php
```

Yii3 DI and middleware pipeline snippets are kept in the root README so the
English and Russian integration guidance stays synchronized.
