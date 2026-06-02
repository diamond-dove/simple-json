# Changelog

All notable changes to `simple-json` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- In-memory JSON toolkit (Phase 1): a static `Json` facade with `Json::parse()` (throws `InvalidJsonException` on malformed JSON) and `Json::tryParse()` (returns `null`), returning an immutable `JsonAccessor` with case-sensitive dot-notation `path()`, `isPresent()`, and strict / `*Or($default)` / `*OrNull()` typed terminals (`string`, `int`, `float`, `bool`, `array`) with no silent coercion. Zero new dependencies.
- `SimpleJsonReader::createFromString()` and `SimpleJsonReader::createFromResource()` for reading a JSON collection from an in-memory string or an already-open stream, in addition to a file path.
- GitHub Actions workflows for tests (lowest/highest dependencies) and code quality (PHPStan + PHP CS Fixer).
- PHPStan (level 6) and PHP CS Fixer configuration, plus `composer analyse` / `composer format` scripts.
- Issue and pull request templates.

### Fixed
- The streaming parser now tracks string context, so structural characters (`{`, `}`, `[`, `]`, `"`) inside string values no longer corrupt parsing of otherwise valid JSON.

### Changed
- Dropped the unused `jajo/jsondb` dependency and moved `spatie/temporary-directory` to `require-dev`.
- Widened `illuminate/support` support to `^12.0 || ^13.0`.
- Added explicit type declarations throughout the source for full PHPStan compliance.

## [2.0.1] - 2026-06-02

### Fixed
- Reader no longer silently drops the trailing items of files large enough to span multiple read chunks.
- `JsonWriter::push()` returns `false` when the underlying write fails instead of always returning `true`.

## [2.0.0] - 2025-06-24

- Full generator-based reading and writing behind the scenes.

## [1.0.2] - 2022-12-14

- New version using generators behind the scenes.

## [1.0.1] - 2022-12-09

- Support for PHP 8.2 and added generator support.

## [1.0.0] - 2022-02-12

- Initial release.

[Unreleased]: https://github.com/diamond-dove/simple-json/compare/v2.0.1...HEAD
[2.0.1]: https://github.com/diamond-dove/simple-json/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/diamond-dove/simple-json/compare/v1.0.2...v2.0.0
[1.0.2]: https://github.com/diamond-dove/simple-json/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/diamond-dove/simple-json/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/diamond-dove/simple-json/releases/tag/v1.0.0
