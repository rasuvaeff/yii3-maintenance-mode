# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.1 — 2026-06-26

- Migrated the test suite from PHPUnit to Testo (`testo/testo`).
- Replaced `phpunit/phpunit` and `testo/bench` with `testo/testo` (bench is bundled).
- Added `testo/bridge-infection`, bumped `infection/infection` to `^0.33` (built-in Testo support).
- `infection.json5` now uses `testFramework: "testo"`; `minMsi` lowered to 92 because five equivalent `ReturnRemoval` mutants on defensive fallback guards cannot be killed by any test.
- Removed `phpunit.xml.dist`; `testo.php` gained a `Unit` suite (benchmarks moved under a separate `Benchmarks` suite).

## 1.0.0 — 2026-06-02

