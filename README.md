# Laravel CSV Export

A simple Laravel package for exporting arrays, collections, generators, and
Eloquent query results to CSV files with memory-friendly streaming.

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13

## Installation

```bash
composer require iamankitthapar/laravel-csv-export
```

## Publish configuration

```bash
php artisan vendor:publish --tag=csv-export-config
```

## Basic usage

```php
use IamAnkitThapar\LaravelCsvExport\Facades\CsvExport;

return CsvExport::download([
    [
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ],
], 'users.csv');
```

## Export an Eloquent collection

```php
use IamAnkitThapar\LaravelCsvExport\Facades\CsvExport;
use App\Models\User;

return CsvExport::download(
    User::query()
        ->select('id', 'name', 'email')
        ->get(),
    'users.csv'
);
```

## Large dataset exports

Avoid loading an entire table with `User::all()`. For large exports, pass a
Laravel lazy query directly to the exporter. Rows are written as they arrive
and are not converted to a normal collection.

`lazyById()` is recommended for very large, ID-based exports:

```php
return CsvExport::download(
    User::query()
        ->select('id', 'name', 'email')
        ->lazyById(1000),
    'users.csv'
);
```

The exporter also accepts `lazy()`, `cursor()`, `LazyCollection`, and PHP
generators:

```php
return CsvExport::download(
    User::query()
        ->select('id', 'name', 'email')
        ->cursor(),
    'users.csv'
);
```

For extremely large database results, prefer `lazy()` or `lazyById()` over
`cursor()`, as PDO drivers may buffer cursor query results.

## Progress callbacks

The optional callback runs after each row is written and receives the
one-based row number plus the original source row:

```php
return CsvExport::download(
    data: User::query()->lazyById(1000),
    filename: 'users.csv',
    progress: function (int $row, mixed $user): void {
        if ($row % 1000 === 0) {
            logger()->info("Exported {$row} users");
        }
    }
);
```

Progress callbacks are available on both `download()` and `store()`.

## Custom headers

```php
return CsvExport::download(
    data: $users,
    filename: 'users.csv',
    headers: [
        'User ID',
        'Full Name',
        'Email Address',
    ]
);
```

## Save to storage

```php
CsvExport::store(
    data: $users,
    path: 'exports/users.csv',
    disk: 'local'
);
```

## Get CSV as a string

```php
$csv = CsvExport::toString($users);
```

`toString()` necessarily holds the final CSV text in memory. Use `download()`
or `store()` for large datasets.

## Testing

```bash
composer test
```

## License

MIT
