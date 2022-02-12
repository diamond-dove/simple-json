# Read and write simple JSON files

This package allows you to easily read and write simple Json files. Behind the scenes generators are used to ensure low memory usage, even when working with large files.

Here's an example on how to read a Json.

```php
use DiamondDove\SimpleJson\SimpleJsonReader;

SimpleJsonReader::create('/', 'users.json')->get()
   ->each(function(array $user) {
        // process the row
    });
```
# Installation
You can install the package via composer:

```
composer require diamon-dove/simple-json
```

# Usage
## Reading a JSON
Imagine you have a JSON with this content.

```json
[
  {"email":  "john@example.com", "first_name":  "John"}, 
  {"email":  "jane@example.com", "first_name":  "jane"}
]
```

```php
use DiamondDove\SimpleJson\SimpleJsonReader;

// $records is an instance of Illuminate\Support\LazyCollection
$records = SimpleJsonReader::create($pathToJson)->get();

$records->each(function(array $user) {
   // in the first pass $user will contain
   // ['email' => 'john@example.com', 'first_name' => 'john']
});
```

### Reading Json file
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

### Manually working with the reader object
Under the hood this package uses the `Jajo\JSONDB` package. You can get to the underlying reader that implements `DiamondDove\SimpleJson\ReaderInterface` by calling the getReader method.

```php
$reader = SimpleJsonReader::Reader::create($pathToJson)->getReader();
```

## Writing files
Here's how you can write a JSON file:

use DiamondDove\SimpleJson\SimpleJsonWriter;
```php
$writer = SimpleJsonWriter::create($pathToJson)
->insert([[
'first_name' => 'John',
'last_name' => 'Doe',
],
[
'first_name' => 'Jane',
'last_name' => 'Doe',
]
]);
```
The file at pathToJson will contain:

```json
[
  {"first_name": "John", "last_name": "Doe"},
  {"first_name":  "Jane", "last_name":  "Doe"}
]
```

### Updating Row
You can also update same JSON file with these methods
```php
SimpleJsonWriter::create($pathToJson)
->where([
'first_name' => 'John',
])
->update([
'first_name' => 'John1',
'last_name' => 'Doe1',
]);
```
Without the where() method, it will update all rows

### Deleting Row

```php
SimpleJsonWriter::create($pathToJson)
->where([
'first_name' => 'John',
])
->delete();
```

Without the where() method, it will update all rows

### Manually working with the writer object
Under the hood this package uses the `Jajo\JSONDB` package. You can get to the underlying writer that implements DiamondDove\SimpleJson\WriterInterface by calling the getWriter method.

```php
$writer = SimpleExcelWriter::create($pathToCsv)->getWriter();
```

# Testing
```sh 
composer test
```

# Contributing
Please see [CONTRIBUTING](https://github.com/diamond-dove/simple-json/blob/main/CONTRIBUTING.md) for details.

# Security
If you discover any security related issues, please email masterfermin02@gmail.com instead of using the issue tracker.

# Credits
- [Fermin Perdomo](https://github.com/masterfermin02)
- [All Contributors](../../contributors)

# License
The MIT License [(MIT)](LICENSE.md). Please see License File for more information.
