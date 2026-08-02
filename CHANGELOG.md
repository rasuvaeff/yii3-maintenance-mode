# Changelog

## 1.1.0 — 2026-08-02

- Add safe header bypass with a backward-compatible query migration switch.
- Add health-path exclusions, IPv4/IPv6 CIDR matching, and trusted proxy client IP resolution.
- Add customizable maintenance response factories and standards-aware content negotiation.
- Add strict file-state parsing, atomic state writes, and the `yii3-maintenance` command.
- Validate `REMOTE_ADDR` with `filter_var()` before using it as a client address.

## 1.0.4 — 2026-07-31

Documentation only — no runtime code, public API or dependency changed.

- Correct the SHA-256 example for `my-secret-token`: the documented hash was the hash of `"test"`, so copying it produced a bypass token nobody could use.
- Replace `(bool) ($_ENV['MAINTENANCE_ENABLED'] ?? false)` with `filter_var(..., FILTER_VALIDATE_BOOL)`: a cast turns `MAINTENANCE_ENABLED=false` into maintenance mode being on.
- Drop the claim that `hash_equals()` means "no brute-force risk", and require a long random token plus TLS. State that the query bypass token reaches access logs, `Referer` headers, browser history and proxies.
- Fix the documented middleware order: `MaintenanceMiddleware` goes before routing and authentication but **inside** `ErrorCatcher`, request ID and observability middleware — it was documented as the outermost entry.
- Replace `echo '{"enabled":true}' > maintenance.json` with an atomic write (temp file in the same directory + `mv`), since the file is re-read on every request.
- Name the `FileMaintenanceProvider` failure behaviour what it is — fail-open — and document that values are coerced, not validated.
- Document the `REMOTE_ADDR` allow-list limits: exact string match, no CIDR, no IPv6 normalization, and the reverse-proxy consequence (`X-Forwarded-For` is deliberately ignored).
- Correct the response customization claim: a `MaintenanceProvider` returns state, not markup; the 503 body is changed by decorating the middleware.
- State that the shipped `config/di.php` and `config/params.php` are applied only when the root application uses the `yiisoft/config` plugin, and show manual wiring.
- List what 1.0.x does not provide (header bypass, path exclusions, CIDR matching, trusted-proxy resolution, custom response factories, atomic writer, console command).
- Mirror all of the above in `README.ru.md`, `llms.txt` and `examples/yii3-app.php`.
- Restore the missing `1.0.1` changelog section, fill in the empty `1.0.0` one, and remove the Keep a Changelog preamble that had been left between the `1.0.3` and `1.0.2` entries.
- Development-only housekeeping, not shipped in the dist archive: apply constructor property promotion in the test doubles (`composer rector` was red) and give `make release-check` the `safe.directory` setting its `bc-check` step needs inside the container.

## 1.0.3 — 2026-06-30

- Add `/benchmarks` and `/Makefile` to `.gitattributes` export-ignore.

## 1.0.2 — 2026-06-26

- Migrated the test suite from PHPUnit to Testo (`testo/testo`).
- Replaced `phpunit/phpunit` and `testo/bench` with `testo/testo` (bench is bundled).
- Added `testo/bridge-infection`, bumped `infection/infection` to `^0.33` (built-in Testo support).
- `infection.json5` now uses `testFramework: "testo"`; `minMsi` lowered to 92 because five equivalent `ReturnRemoval` mutants on defensive fallback guards cannot be killed by any test.
- Removed `phpunit.xml.dist`; `testo.php` gained a `Unit` suite (benchmarks moved under a separate `Benchmarks` suite).

## 1.0.1 — 2026-06-26

- Align quality tooling, CI workflows and documentation with the package template.
- Require PHP 8.3+ and make eligible classes `final readonly`.
- Harden CI workflows: SHA-pinned actions, `permissions: { contents: read }`, `persist-credentials: false`.
- Add a benchmarks suite and the `make audit-package` target.
- Migrate the test suite from PHPUnit to Testo; the tooling settings were finalised in 1.0.2.

## 1.0.0 — 2026-06-02

- Initial release: `MaintenanceMiddleware` (PSR-15) answering 503 with a `Retry-After` header while maintenance is on.
- `MaintenanceState` value object and the `MaintenanceProvider` interface, with `ConfigMaintenanceProvider` and `FileMaintenanceProvider` implementations.
- Content negotiation: JSON for `Accept: application/json` or a missing `Accept`, HTML otherwise.
- IP allow-list and a bypass token compared with `hash_equals()`.
- Ship `config/di.php` and `config/params.php` for the `yiisoft/config` plugin; bind `MaintenanceProvider` to `ConfigMaintenanceProvider` by default.
