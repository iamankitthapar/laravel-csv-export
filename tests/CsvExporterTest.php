<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use IamAnkitThapar\LaravelCsvExport\CsvExporter;

it('converts an array to csv text', function (): void {
    config()->set('csv-export.utf8_bom', false);

    $exporter = app(CsvExporter::class);

    $csv = $exporter->toString([
        [
            'id' => 1,
            'name' => 'Jon',
        ],
        [
            'id' => 2,
            'name' => 'Roy',
        ],
    ]);

    expect($csv)
        ->toContain('id,name')
        ->toContain('1,Jon')
        ->toContain('2,Roy');
});

it('supports custom headers', function (): void {
    config()->set('csv-export.utf8_bom', false);

    $exporter = app(CsvExporter::class);

    $csv = $exporter->toString(
        [
            [
                'id' => 1,
                'name' => 'Jon',
            ],
        ],
        [
            'User ID',
            'Full Name',
        ]
    );

    expect($csv)
        ->toContain('"User ID","Full Name"')
        ->toContain('1,Jon');
});

it('adds csv extension to download filename', function (): void {
    $exporter = app(CsvExporter::class);

    $response = $exporter->download(
        [
            ['name' => 'Jon'],
        ],
        'users'
    );

    expect(
        $response->headers->get('content-disposition')
    )->toContain('users.csv');
});

it('stores a csv file', function (): void {
    config()->set('csv-export.utf8_bom', false);

    Storage::fake('local');

    $exporter = app(CsvExporter::class);

    $path = $exporter->store(
        [
            [
                'id' => 1,
                'name' => 'Jon',
            ],
        ],
        'exports/users.csv',
        disk: 'local'
    );

    expect($path)->toBe('exports/users.csv');

    Storage::disk('local')->assertExists('exports/users.csv');
});