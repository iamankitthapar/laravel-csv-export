# Laravel CSV Export

A simple Laravel package for exporting arrays, collections, and Eloquent query
results to CSV files.

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

## Testing

```bash
composer test
```

## License

MIT