<?php

declare(strict_types=1);

namespace IamAnkitThapar\LaravelCsvExport;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
    /**
     * Download data as a streamed CSV file.
     *
     * @param iterable<mixed>|Arrayable<mixed> $data
     * @param array<string>|null $headers
     * @param callable(int, mixed): void|null $progress
     */
    public function download(
        iterable|Arrayable $data,
        string $filename = 'export.csv',
        ?array $headers = null,
        ?string $delimiter = null,
        ?callable $progress = null
    ): StreamedResponse {
        $filename = $this->normalizeFilename($filename);
        $delimiter ??= (string) config('csv-export.delimiter', ',');

        return response()->streamDownload(
            function () use ($data, $headers, $delimiter, $progress): void {
                $handle = fopen('php://output', 'wb');

                if ($handle === false) {
                    throw new InvalidArgumentException(
                        'Unable to open the CSV output stream.'
                    );
                }

                try {
                    $this->write(
                        $handle,
                        $data,
                        $headers,
                        $delimiter,
                        $progress
                    );
                } finally {
                    fclose($handle);
                }
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache',
            ]
        );
    }

    /**
     * Save CSV data to a Laravel storage disk.
     *
     * @param iterable<mixed>|Arrayable<mixed> $data
     * @param array<string>|null $headers
     * @param callable(int, mixed): void|null $progress
     */
    public function store(
        iterable|Arrayable $data,
        string $path,
        ?array $headers = null,
        ?string $disk = null,
        ?string $delimiter = null,
        ?callable $progress = null
    ): string {
        $disk ??= (string) config('csv-export.disk', 'local');
        $delimiter ??= (string) config('csv-export.delimiter', ',');

        // Spill exports larger than 5 MiB to a temporary file automatically.
        $stream = fopen('php://temp/maxmemory:5242880', 'w+b');

        if ($stream === false) {
            throw new InvalidArgumentException(
                'Unable to create a temporary CSV stream.'
            );
        }

        try {
            $this->write($stream, $data, $headers, $delimiter, $progress);

            rewind($stream);
            $saved = Storage::disk($disk)->put($path, $stream);
        } finally {
            fclose($stream);
        }

        if ($saved === false) {
            throw new InvalidArgumentException(
                "Unable to save the CSV file to disk [{$disk}]."
            );
        }

        return $path;
    }

    /**
     * Convert data into CSV text.
     *
     * This method keeps the resulting string in memory. Prefer download() or
     * store() for large exports.
     *
     * @param iterable<mixed>|Arrayable<mixed> $data
     * @param array<string>|null $headers
     */
    public function toString(
        iterable|Arrayable $data,
        ?array $headers = null,
        ?string $delimiter = null
    ): string {
        $delimiter ??= (string) config('csv-export.delimiter', ',');
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new InvalidArgumentException(
                'Unable to create a temporary CSV stream.'
            );
        }

        try {
            $this->write($stream, $data, $headers, $delimiter);

            rewind($stream);
            $contents = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        if ($contents === false) {
            throw new InvalidArgumentException(
                'Unable to read the generated CSV data.'
            );
        }

        return $contents;
    }

    /**
     * @param resource $handle
     * @param iterable<mixed>|Arrayable<mixed> $data
     * @param array<string>|null $headers
     * @param callable(int, mixed): void|null $progress
     */
    private function write(
        $handle,
        iterable|Arrayable $data,
        ?array $headers,
        string $delimiter,
        ?callable $progress = null
    ): void {
        if ((bool) config('csv-export.utf8_bom', true)) {
            fwrite($handle, "\xEF\xBB\xBF");
        }

        $columnKeys = null;
        $rowNumber = 0;

        foreach ($this->toIterable($data) as $row) {
            $normalizedRow = $this->normalizeRow($row);

            if ($columnKeys === null) {
                $columnKeys = array_keys($normalizedRow);
                $csvHeaders = $headers ?? $columnKeys;

                if ($csvHeaders !== []) {
                    $this->writeRow($handle, $csvHeaders, $delimiter);
                }
            }

            $this->writeRow(
                $handle,
                $this->orderRowByHeaders($normalizedRow, $columnKeys),
                $delimiter
            );

            $rowNumber++;

            if ($progress !== null) {
                $progress($rowNumber, $row);
            }
        }
    }

    /**
     * @param iterable<mixed>|Arrayable<mixed> $data
     * @return iterable<mixed>
     */
    private function toIterable(iterable|Arrayable $data): iterable
    {
        if ($data instanceof Arrayable && ! is_iterable($data)) {
            return $data->toArray();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRow(mixed $row): array
    {
        if ($row instanceof Arrayable) {
            $row = $row->toArray();
        } elseif (is_object($row)) {
            $row = get_object_vars($row);
        }

        if (! is_array($row)) {
            return ['value' => $this->normalizeValue($row)];
        }

        return array_map(
            fn (mixed $value): mixed => $this->normalizeValue($value),
            $row
        );
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            $json = json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            return $json === false ? '' : $json;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string> $keys
     * @return array<int, mixed>
     */
    private function orderRowByHeaders(array $row, array $keys): array
    {
        return array_map(
            static fn (string $key): mixed => $row[$key] ?? '',
            $keys
        );
    }

    /**
     * @param resource $handle
     * @param array<mixed> $row
     */
    private function writeRow($handle, array $row, string $delimiter): void
    {
        fputcsv(
            $handle,
            $row,
            $delimiter,
            (string) config('csv-export.enclosure', '"'),
            (string) config('csv-export.escape', '')
        );
    }

    private function normalizeFilename(string $filename): string
    {
        $filename = trim($filename);

        if ($filename === '') {
            return 'export.csv';
        }

        if (! str_ends_with(strtolower($filename), '.csv')) {
            $filename .= '.csv';
        }

        return basename($filename);
    }
}
