# Simple JSON File Reader and Writer
This package makes it easy to read and write simple JSON files. It uses generators to minimize memory usage, even when dealing with large files.

Here is an example of how to read a JSON file:

```php
use DiamondDove\SimpleJson\SimpleJsonReader;

SimpleJsonReader::create('users.json')->get()
   ->each(function(array $user) {
        // process the row
    });
```
# Installation
You can install the package using composer:

```
composer require diamon-dove/simple-json
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

## Writing files
To write a JSON file, you can use the following code:

use DiamondDove\SimpleJson\SimpleJsonWriter;
```php
$writer = SimpleJsonWriter::create($pathToJson)
->push([[
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
