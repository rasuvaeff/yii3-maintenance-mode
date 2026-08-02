# Examples

| Script | Shows | Needs server? |
|---|---|---|
| `basic.php` | Immutable config-backed state | No |
| `file-toggle.php` | Atomic file enable/disable and strict reading | No |

## Running

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/basic.php
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/file-toggle.php
```

Yii3 DI and middleware pipeline snippets are kept in the root README so the
English and Russian integration guidance stays synchronized.
