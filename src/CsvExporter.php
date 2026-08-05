<?php

declare(strict_types=1);

namespace IamAnkitThapar\LaravelCsvExport;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Traversable;

class CsvExporter
{
    /**
     * Download data as a CSV file.
     *
     * @param iterable<mixed>|Arrayable<mixed>|array<mixed> $data
     * @param array<string>|null $headers
     */
    public function download(
        iterable|Arrayable $data,
        string $filename = 'export.csv',
        ?array $headers = null,
        ?string $delimiter = null
    ): StreamedResponse {
        $filename = $this->normalizeFilename($filename);
        $delimiter ??= (string) config('csv-export.delimiter', ',');

        return response()->streamDownload(
            function () use ($data, $headers, $delimiter): void {
                $handle = fopen('php://output', 'wb');

                if ($handle === false) {
                    throw new InvalidArgumentException(
                        'Unable to open the CSV output stream.'
                    );
                }

                if ((bool) config('csv-export.utf8_bom', true)) {
                    fwrite($handle, "\xEF\xBB\xBF");
                }

                $rows = $this->normalizeData($data);

                $firstRow = $rows->first();

                if ($firstRow === null) {
                    fclose($handle);

                    return;
                }

                $firstRow = $this->normalizeRow($firstRow);

                $csvHeaders = $headers ?? array_keys($firstRow);

                if ($csvHeaders !== []) {
                    $this->writeRow($handle, $csvHeaders, $delimiter);
                }

                foreach ($rows as $row) {
                    $normalizedRow = $this->normalizeRow($row);

                    $orderedRow = $this->orderRowByHeaders(
                        $normalizedRow,
                        array_keys($firstRow)
                    );

                    $this->writeRow($handle, $orderedRow, $delimiter);
                }

                fclose($handle);
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
     * @param iterable<mixed>|Arrayable<mixed>|array<mixed> $data
     * @param array<string>|null $headers
     */
    public function store(
        iterable|Arrayable $data,
        string $path,
        ?array $headers = null,
        ?string $disk = null,
        ?string $delimiter = null
    ): string {
        $disk ??= (string) config('csv-export.disk', 'local');
        $delimiter ??= (string) config('csv-export.delimiter', ',');

        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new InvalidArgumentException(
                'Unable to create a temporary CSV stream.'
            );
        }

        if ((bool) config('csv-export.utf8_bom', true)) {
            fwrite($stream, "\xEF\xBB\xBF");
        }

        $rows = $this->normalizeData($data);
        $firstRow = $rows->first();

        if ($firstRow !== null) {
            $firstRow = $this->normalizeRow($firstRow);
            $csvHeaders = $headers ?? array_keys($firstRow);

            if ($csvHeaders !== []) {
                $this->writeRow($stream, $csvHeaders, $delimiter);
            }

            foreach ($rows as $row) {
                $normalizedRow = $this->normalizeRow($row);

                $orderedRow = $this->orderRowByHeaders(
                    $normalizedRow,
                    array_keys($firstRow)
                );

                $this->writeRow($stream, $orderedRow, $delimiter);
            }
        }

        rewind($stream);

        $saved = Storage::disk($disk)->put($path, $stream);

        fclose($stream);

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
     * @param iterable<mixed>|Arrayable<mixed>|array<mixed> $data
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

        if ((bool) config('csv-export.utf8_bom', true)) {
            fwrite($stream, "\xEF\xBB\xBF");
        }

        $rows = $this->normalizeData($data);
        $firstRow = $rows->first();

        if ($firstRow !== null) {
            $firstRow = $this->normalizeRow($firstRow);
            $csvHeaders = $headers ?? array_keys($firstRow);

            if ($csvHeaders !== []) {
                $this->writeRow($stream, $csvHeaders, $delimiter);
            }

            foreach ($rows as $row) {
                $normalizedRow = $this->normalizeRow($row);

                $orderedRow = $this->orderRowByHeaders(
                    $normalizedRow,
                    array_keys($firstRow)
                );

                $this->writeRow($stream, $orderedRow, $delimiter);
            }
        }

        rewind($stream);

        $contents = stream_get_contents($stream);

        fclose($stream);

        if ($contents === false) {
            throw new InvalidArgumentException(
                'Unable to read the generated CSV data.'
            );
        }

        return $contents;
    }

    /**
     * @param iterable<mixed>|Arrayable<mixed>|array<mixed> $data
     */
    private function normalizeData(
        iterable|Arrayable $data
    ): Collection {
        if ($data instanceof Collection) {
            return $data;
        }

        if ($data instanceof Arrayable) {
            return collect($data->toArray());
        }

        if ($data instanceof Traversable) {
            return collect(iterator_to_array($data));
        }

        return collect($data);
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
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
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
    private function writeRow(
        $handle,
        array $row,
        string $delimiter
    ): void {
        fputcsv(
            $handle,
            $row,
            $delimiter,
            (string) config('csv-export.enclosure', '"'),
            (string) config('csv-export.escape', '\\')
        );
    }

    private function normalizeFilename(string $filename): string
    {
        $filename = trim($filename);

        if ($filename === '') {
            $filename = 'export.csv';
        }

        if (! str_ends_with(strtolower($filename), '.csv')) {
            $filename .= '.csv';
        }

        return basename($filename);
    }
}