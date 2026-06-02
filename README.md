# Simple JSON File Reader and Writer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/diamond-dove/simple-json.svg?style=flat-square)](https://packagist.org/packages/diamond-dove/simple-json)
[![Tests](https://img.shields.io/github/actions/workflow/status/diamond-dove/simple-json/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/diamond-dove/simple-json/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/diamond-dove/simple-json.svg?style=flat-square)](https://packagist.org/packages/diamond-dove/simple-json)
[![License](https://img.shields.io/packagist/l/diamond-dove/simple-json.svg?style=flat-square)](LICENSE.md)

This package makes it easy to read and write simple JSON files. It uses generators to minimize memory usage, even when dealing with large files.

Here is an example of how to read a JSON file:

```php
use DiamondDove\SimpleJson\SimpleJsonReader;

SimpleJsonReader::create('users.json')->get()
   ->each(function(array $user) {
        // process the row
    });
```
# Requirements
- PHP 8.4 or higher

# Installation
You can install the package using composer:

```
composer require diamond-dove/simple-json
```

# Usage
## Reading a JSON
Suppose you have a JSON file with the following content:

```json
[
  {"email":  "john@example.com", "first_name":  "John"}, 
  {"email":  "jane@example.com", "first_name":  "jane"}
]
```

To read this file in PHP, you can do the following:

```php
use DiamondDove\SimpleJson\SimpleJsonReader;

// $records is an instance of Illuminate\Support\LazyCollection
$records = SimpleJsonReader::create($pathToJson)->get();

$records->each(function(array $user) {
   // in the first pass $user will contain
   // ['email' => 'john@example.com', 'first_name' => 'john']
});
```

### Working with LazyCollections
`get` will return an instance of `Illuminate\Support\LazyCollection`. This class is part of the Laravel framework. Behind the scenes generators are used, so memory usage will be low, even for large files.

You'll find a list of methods you can use on a `LazyCollection` [in the Laravel documentation.](https://laravel.com/docs/master/collections#the-enumerable-contract)

Here's a quick, silly example where we only want to process rows that have a first_name that contains more than 5 characters.
You'll find a list of methods you can use on a LazyCollection in the Laravel documentation.

Here's a quick, silly example where we only want to process elements that have a first_name that contains more than 5 characters.
```php
SimpleJsonReader::create($pathToJson)->get()
->filter(function(array $user) {
return strlen($user['first_name']) > 5;
})
->each(function(array $user) {
// processing user
});
```

### Reading from a string or a stream
You don't have to read from a file. If you already have the JSON in a string,
or in an open stream resource, you can read from it directly:

```php
use DiamondDove\SimpleJson\SimpleJsonReader;

// From a string
SimpleJsonReader::createFromString('[{"name": "John"}, {"name": "Jane"}]')
    ->get()
    ->each(function (array $user) {
        // process the row
    });

// From an open stream resource (you keep ownership of the resource)
$stream = fopen('php://temp', 'r+b');
fwrite($stream, '[{"name": "John"}]');

SimpleJsonReader::createFromResource($stream)->get()->each(/* ... */);

fclose($stream);
```

## Writing files
To write a JSON file, you can use the following code:

```php
use DiamondDove\SimpleJson\SimpleJsonWriter;

$writer = SimpleJsonWriter::create($pathToJson)
    ->push([
        [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ],
        [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ],
    ]);
```
The file at pathToJson will contain:

```json
[
  {"first_name": "John", "last_name": "Doe"},
  {"first_name":  "Jane", "last_name":  "Doe"}
]
```

You can also use:
```php
SimpleJsonWriter::create($this->pathToJson)
                        ->push([
                            'name'  => 'Thomas',
                            'state' => 'Nigeria',
                            'age'   => 22,
                        ])
                        ->push([
                            'name'  => 'Luis',
                            'state' => 'Nigeria',
                            'age'   => 32,
                        ]);
```

# In-memory JSON toolkit

Besides streaming whole files, the package ships a small, framework-agnostic toolkit
for working with a single JSON document in memory: safe parsing, dot-path access with
strict typed extraction, and validation — all via the static `Json` facade, with zero
extra dependencies.

## Safe parsing & typed access

`Json::parse()` decodes with `JSON_THROW_ON_ERROR` and, on malformed JSON, throws a
`DiamondDove\SimpleJson\Exceptions\InvalidJsonException` (the native `JsonException` is
chained as `$previous`) — so you never have to second-guess `json_decode()`'s ambiguous
`null` return. Use `Json::tryParse()` for a `null`-on-failure variant that never throws.

```php
use DiamondDove\SimpleJson\Json;

$json = '{"user": {"name": "Ana", "age": 30, "address": {"city": "Santo Domingo"}}}';

$city = Json::parse($json)->path('user.address.city')->string();   // 'Santo Domingo'
$age  = Json::parse($json)->path('user.age')->int();               // 30

// Exception-free
$accessor = Json::tryParse($maybeJson);   // null when the JSON is invalid
```

`path()` walks dot-notation (case-sensitive) and returns another accessor. The typed
terminals come in three flavours:

```php
$user = Json::parse($json)->path('user');

$user->path('name')->string();          // strict: throws JsonTypeException on a type mismatch
$user->path('age')->int();              // strict int (rejects 30.0 and "30")
$user->path('nickname')->stringOr('—'); // lenient: returns the default when missing/mismatched
$user->path('nickname')->stringOrNull();// lenient: returns null when missing/mismatched
$user->path('age')->isPresent();        // true — distinguishes a present null from a missing key
```

Terminals are **strict — no silent coercion**: `int` requires a real integer, `float`
widens an integer to float (`5` → `5.0`), `bool` rejects `0`/`1`. The full set is
`string`, `int`, `float`, `bool`, `array`, each with `*Or($default)` and `*OrNull()`
variants.

> **Limitation:** `path()` uses dot-notation; a JSON key that contains a literal dot (e.g. `"weird.key"`) is matched as a whole key first by the underlying resolver, while a genuinely nested `{"weird":{"key":...}}` is what dot-segmentation targets — avoid literal dots in keys you intend to traverse.

## Validation

`Json::validate()` checks a JSON document against Laravel-style rules using a tiny
in-house engine — **no `illuminate/validation`** and no other new dependency. Rules are
written as pipe strings or arrays, and fields are addressed with the same dot-notation as
`path()`.

```php
use DiamondDove\SimpleJson\Json;

$result = Json::validate('{"email": "ana@example.com", "age": 30}', [
    'email'     => 'required|email',
    'age'       => 'int|min:18',
    'user.name' => 'required|string',   // dot-notation, case-sensitive
    'tags'      => ['nullable', 'array'],
]);

$result->passes();     // bool
$result->fails();      // bool
$result->errors();     // ['user.name' => ['The user.name field is required.']]
$result->validated();  // array of validated fields; throws JsonValidationException on failure
```

Supported rules: `required`, `nullable`, `string`, `int`, `numeric`, `bool`, `array`,
`email`, `min`, `max`, `between`, `in`, `regex`. Type rules are **strict** (consistent
with the accessor): `int`/`numeric` reject numeric strings, `bool` rejects `0`/`1`.
`min`/`max`/`between` are inclusive and type-aware (number magnitude, string length, or
array count). An unknown rule or a missing rule parameter throws `\InvalidArgumentException`
(it's a programming error, not a validation failure).

## Mapping to typed objects (DTOs)

`Json::map()` hydrates a plain PHP class from a JSON string (or an already-decoded array)
using constructor property promotion — no setters, no reflection-written private
properties, and no heavy mapping dependency. It maps source keys to constructor
parameters by name, recurses into nested DTOs, hydrates backed enums, and maps lists of
DTOs via the `#[ListOf]` attribute.

```php
use DiamondDove\SimpleJson\Json;
use DiamondDove\SimpleJson\Mapping\Attributes\ListOf;

enum Status: string {
    case Active = 'active';
    case Inactive = 'inactive';
}

final class Address {
    public function __construct(
        public readonly string $city,
        public readonly ?string $zip = null,
    ) {}
}

final class Tag {
    public function __construct(public readonly string $label) {}
}

final class User {
    public function __construct(
        public readonly string $name,
        public readonly int $age,
        public readonly ?Address $address = null,            // nested DTO
        public readonly Status $status = Status::Active,     // backed enum
        #[ListOf(Tag::class)] public readonly array $tags = [], // list of DTOs
    ) {}
}

$user = Json::map('{
    "name": "Ana", "age": 30,
    "address": {"city": "Santo Domingo"},
    "status": "active",
    "tags": [{"label": "vip"}, {"label": "beta"}]
}', User::class);

$user->address->city;   // 'Santo Domingo'
$user->status;          // Status::Active
$user->tags[0]->label;  // 'vip'
```

Mapping is **strict**, mirroring the rest of the toolkit: a type mismatch, a missing
required parameter, or an invalid enum value throws a
`DiamondDove\SimpleJson\Exceptions\JsonMappingException` (which also implements the
`JsonException` marker, so a thrown DTO-constructor exception is wrapped and chained as
`$previous`). Extra source keys are ignored.

Because `Json::map()` also accepts a decoded array, it composes directly with the streaming
reader — hydrate every row of a huge file into typed objects without loading the whole file:

```php
use DiamondDove\SimpleJson\SimpleJsonReader;

SimpleJsonReader::create('users.json')->get()
    ->map(fn (array $row) => Json::map($row, User::class))
    ->each(function (User $user) {
        // strongly-typed, one row at a time, low memory
    });
```

## JSONPath queries (optional)

For queries that go beyond the core dot-notation `path()` — recursive descent,
wildcards, array slices, filter expressions — `Json::query()` wraps the
[`softcreatr/jsonpath`](https://github.com/SoftCreatR/JSONPath) package. It is an
**optional** dependency: the toolkit core stays dependency-free, and you only pull it in
if you need full JSONPath.

```sh
composer require softcreatr/jsonpath
```

```php
use DiamondDove\SimpleJson\Json;

$json = '{"store": {"book": [
    {"title": "A", "price": 8.95},
    {"title": "B", "price": 12.99}
]}}';

Json::query($json, '$..book[?(@.price < 10)].title');  // ['A']
Json::query($json, '$.store.book[*].title');           // ['A', 'B']
Json::query($json, '$..nonexistent');                  // []  (empty match)
```

If the package is not installed, `Json::query()` throws a
`DiamondDove\SimpleJson\Exceptions\MissingDependencyException` whose message tells you
exactly what to install. An invalid JSONPath expression throws `JsonQueryException`, and
malformed JSON throws `InvalidJsonException` — all three implement the `JsonException`
marker, so you can catch them uniformly.

## JSON Schema validation (optional)

When you already have a JSON Schema, `Json::validateSchema()` validates a document against
it by wrapping the optional [`opis/json-schema`](https://github.com/opis/json-schema)
package (multiple drafts). It complements the built-in rule engine.

```sh
composer require opis/json-schema
```

```php
use DiamondDove\SimpleJson\Json;

$schema = '{"type":"object","required":["age"],"properties":{"age":{"type":"integer","minimum":0}}}';

Json::matchesSchema('{"age":30}', $schema);   // true
Json::matchesSchema('{"age":-1}', $schema);   // false

$result = Json::validateSchema('{"age":-1}', $schema);
$result->passes();   // false
$result->errors();   // ['/age: Number must be greater than or equal to 0']
```

Remote `$ref`s are **not** fetched (no network access), so the validator is safe against
SSRF. A structurally invalid schema throws `JsonSchemaException`; a document that simply
doesn't conform is reported through the result (not an exception).

## Advanced typed mapping (optional)

`Json::map()` covers plain DTOs. For advanced type signatures it can't express —
`list<string>`, `int<0,100>`, `non-empty-string`, shaped arrays, generics — `Json::mapTo()`
wraps the optional [`cuyz/valinor`](https://github.com/CuyZ/Valinor) mapper.

```sh
composer require cuyz/valinor
```

```php
use DiamondDove\SimpleJson\Json;

Json::mapTo('["a", "b", "c"]', 'list<string>');                 // ['a', 'b', 'c']
Json::mapTo('{"name": "Ana", "age": 30}', 'array{name: string, age: int}');
Json::mapTo('200', 'int<0, 100>');   // throws JsonMappingException (out of range)
```

Data that violates the signature throws `JsonMappingException`; an invalid signature is a
programming error and throws `\InvalidArgumentException`.

# Testing
```sh
composer test       # run the test suite
composer analyse    # run PHPStan static analysis
composer format     # apply the code style fixes
```

# Changelog
Please see [CHANGELOG](CHANGELOG.md) for details on what has changed recently.

# Contributing
Please see [CONTRIBUTING](https://github.com/diamond-dove/simple-json/blob/main/CONTRIBUTING.md) for details.

# Security
If you discover any security related issues, please email masterfermin02@gmail.com instead of using the issue tracker.

# Credits
- [Fermin Perdomo](https://github.com/masterfermin02)
- [All Contributors](../../contributors)

# License
The MIT License [(MIT)](LICENSE.md). Please see License File for more information.
