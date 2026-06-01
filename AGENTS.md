# AGENTS.md — yii3-maintenance-mode

Guidance for AI agents working on this package. Read before changing code.

## What this is

Maintenance mode PSR-15 middleware for Yii3. Provides `MaintenanceMiddleware` that returns HTTP 503
with `Retry-After` header when maintenance mode is enabled. Supports IP allow-list and timing-safe
bypass tokens. Content negotiation: JSON for `Accept: application/json` (default), HTML otherwise.

Namespace: `Rasuvaeff\Yii3MaintenanceMode`.
Public API: `MaintenanceMiddleware`, `MaintenanceState`, `MaintenanceProvider`,
`ConfigMaintenanceProvider`, `FileMaintenanceProvider`.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Timing-safe comparison.** Bypass tokens MUST use `hash_equals()`. Never use `===` for secrets.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
```

## Invariants & gotchas

- `MaintenanceState` is `readonly` — all fields are immutable after construction.
- `FileMaintenanceProvider` wraps `json_decode` in try-catch for `JsonException` — graceful fallback to disabled.
- Content negotiation: `str_contains($accept, 'application/json') || $accept === ''` -> JSON.
- `REMOTE_ADDR` from `getServerParams()` can be non-string — always validate with `is_string()`.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build` and paste the output.
