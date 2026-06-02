# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`diamond-dove/simple-json` is a PHP library (PHP ^8.4) for reading and writing large JSON files using PHP generators, so memory stays low regardless of file size. Reads return an `Illuminate\Support\LazyCollection`; writes stream items to disk one at a time. PSR-4 namespace `DiamondDove\SimpleJson\` maps to `src/`.

## Commands

```sh
composer test                 # run the full PHPUnit suite
composer test-coverage        # run with HTML coverage report into coverage/
vendor/bin/phpunit --filter testCanGetLargeFile   # run a single test by method name
vendor/bin/phpunit tests/SimpleJsonReaderTest.php # run one test file
```

Note: `composer format` invokes `vendor/bin/php-cs-fixer`, but php-cs-fixer is **not** in `require-dev` and is not installed in `vendor/bin`. The command will fail unless the tool is installed separately. CONTRIBUTING.md states the target style is PSR-2.

## Architecture

There are two parallel pipelines — a read path and a write path — each fronted by a static `create()` factory entry point. The user-facing classes delegate through a factory to a thin facade, which delegates to the streaming engine that does the real work.

**Read path** (`SimpleJsonReader::create($path)->get()`):
- `SimpleJsonReader` → `ReaderFactory::createFromFile()` → `SimpleJsonDB` (implements `ReaderInterface`) → `JsonDb` → `Reader/JsonCollectionStreamReader`.
- `JsonCollectionStreamReader` is the core. It `fread`s the file in 8192-byte chunks and parses character-by-character, tracking `nestingLevel` of `{`/`}` to identify top-level objects within the array, yielding each as it completes. It is multibyte-safe (`mb_str_split`/`mb_substr`) and keeps an unparsed buffer tail across chunk boundaries. Never loads the whole file into memory.
- `SimpleJsonDB::get()` wraps the generator in `LazyCollection::make()`. It **swallows** the underlying "json is invalid" error and returns an empty collection instead of throwing; any other exception is rethrown.

**Write path** (`SimpleJsonWriter::create($path)->push($record)`):
- `SimpleJsonWriter` → `WriterFactory::createFromFile()` → `Writer/JsonWriter` (implements `WriterInterface`) → `Writer/JsonCollectionStreamWriter`.
- `JsonCollectionStreamWriter` opens the file in `wb` mode, writes the opening `[` in its constructor, `json_encode`s each pushed item (comma-separated), and writes the closing `]` in `close()`. **`close()` runs from `__destruct()`**, so a file is only valid JSON once the writer object is garbage-collected — there is no explicit flush/finalize call in the public API. `push()` accepts either a single record or an array of records.

**Shared helpers:**
- `Traits/PathHandle` splits paths via `pathinfo` and **lowercases** the result (`getBaseName`, `getDirName`). Filenames are therefore case-insensitive throughout.
- `JsonDb::from()` strips any `.json` extension and re-appends it, building `{$dir}/{$file}.json`.
- `ReaderInterface` exposes `select()`/`from()` (SQL-like), but the streaming reader returns whole objects — `select($column)` is stored but does not actually project columns. Filtering/selection is expected to be done on the returned `LazyCollection` (e.g. `->where(...)`, `->filter(...)`).

## Tests

- Built on PHPUnit 12 with `spatie/phpunit-snapshot-assertions`. `tests/TestCase` enables the `MatchesSnapshots` trait.
- Input fixtures live in `tests/stubs/` (e.g. `users.json`, `twitter_example_0.json` for the large-file path, `emptyFile.json`). Snapshot output lives in `tests/__snapshots__/`; writer tests assert against these with `assertMatchesFileSnapshot()`. Delete the relevant snapshot file to regenerate it on the next run.
- Writer tests write into a `Spatie\TemporaryDirectory\TemporaryDirectory` under `tests/temp`.
