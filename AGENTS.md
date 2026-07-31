# AGENTS.md — yii3-maintenance-mode

Guidance for AI agents working on this package. Read before changing code.

## What this is

Maintenance mode PSR-15 middleware for Yii3. Provides `MaintenanceMiddleware` that returns HTTP 503
with `Retry-After` header when maintenance mode is enabled. Supports IP allow-list and timing-safe
bypass tokens. Content negotiation: JSON for `Accept: application/json` (default), HTML otherwise.

Namespace: `Rasuvaeff\Yii3MaintenanceMode`.
Public API: `MaintenanceMiddleware`, `MaintenanceState`, `MaintenanceProvider`,
`ConfigMaintenanceProvider`, `FileMaintenanceProvider`, `MaintenanceAccessPolicy`,
`DefaultMaintenanceAccessPolicy`, `ClientIpResolver`, `RemoteAddrClientIpResolver`,
`TrustedProxyClientIpResolver`, `MaintenanceResponseFactory`,
`DefaultMaintenanceResponseFactory`, `AtomicMaintenanceFileWriter`.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Safe request trust.** Bypass tokens MUST use `hash_equals()` and forwarded
   addresses MUST only be read from explicitly trusted proxies.
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
- `FileMaintenanceProvider` accepts strict JSON types and falls back to disabled state.
- File state changes use `AtomicMaintenanceFileWriter`; never truncate the live file.
- Header bypass is preferred; legacy query bypass remains configurable for migration.
- Content negotiation honors JSON suffix media types and quality values.
- `REMOTE_ADDR` is accepted only after `filter_var(..., FILTER_VALIDATE_IP)` succeeds.
- Forwarded headers are ignored unless `TrustedProxyClientIpResolver` trusts the direct peer.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.

## When you finish

- Update `README.md` and `README.ru.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build` and paste the output.
