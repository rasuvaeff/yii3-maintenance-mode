# Examples

| Script | Shows | Needs server? |
|---|---|---|
| `basic.php` | ConfigMaintenanceProvider, reading state | No |
| `yii3-app.php` | Full Yii3 wiring: params, DI, middleware pipeline, bypass token, console command | No (docs) |

## Running

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/basic.php
```

`yii3-app.php` is documentation-as-code — copy the relevant sections into your application.
