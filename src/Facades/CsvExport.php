<?php

declare(strict_types=1);

namespace IamAnkitThapar\LaravelCsvExport\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Symfony\Component\HttpFoundation\StreamedResponse download(
 *     iterable|\Illuminate\Contracts\Support\Arrayable $data,
 *     string $filename = 'export.csv',
 *     ?array $headers = null,
 *     ?string $delimiter = null,
 *     ?callable $progress = null
 * )
 *
 * @method static string store(
 *     iterable|\Illuminate\Contracts\Support\Arrayable $data,
 *     string $path,
 *     ?array $headers = null,
 *     ?string $disk = null,
 *     ?string $delimiter = null,
 *     ?callable $progress = null
 * )
 *
 * @method static string toString(
 *     iterable|\Illuminate\Contracts\Support\Arrayable $data,
 *     ?array $headers = null,
 *     ?string $delimiter = null
 * )
 */
class CsvExport extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'csv-export';
    }
}
