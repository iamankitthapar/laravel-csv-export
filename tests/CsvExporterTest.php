<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
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

it('exports generator data', function (): void {
    config()->set('csv-export.utf8_bom', false);

    $generator = function (): Generator {
        yield ['id' => 1, 'name' => 'Ankit'];
        yield ['id' => 2, 'name' => 'John'];
    };

    $csv = app(CsvExporter::class)->toString($generator());

    expect($csv)
        ->toContain('id,name')
        ->toContain('1,Ankit')
        ->toContain('2,John');
});

it('supports lazy collections', function (): void {
    config()->set('csv-export.utf8_bom', false);

    $users = LazyCollection::make(function (): Generator {
        for ($i = 1; $i <= 1000; $i++) {
            yield ['id' => $i, 'name' => "User {$i}"];
        }
    });

    $csv = app(CsvExporter::class)->toString($users);

    expect($csv)
        ->toContain('id,name')
        ->toContain('1,"User 1"')
        ->toContain('1000,"User 1000"');
});

it('reports download progress after each exported row', function (): void {
    config()->set('csv-export.utf8_bom', false);

    $processed = [];
    $response = app(CsvExporter::class)->download(
        data: [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ],
        progress: function (int $row, mixed $source) use (&$processed): void {
            $processed[] = [$row, $source['id']];
        }
    );

    ob_start();
    $response->sendContent();
    ob_end_clean();

    expect($processed)->toBe([
        [1, 1],
        [2, 2],
        [3, 3],
    ]);
});

it('processes generator rows one at a time when storing', function (): void {
    config()->set('csv-export.utf8_bom', false);
    Storage::fake('local');

    $events = [];
    $generator = function () use (&$events): Generator {
        for ($i = 1; $i <= 3; $i++) {
            $events[] = "yield:{$i}";
            yield ['id' => $i];
        }
    };

    app(CsvExporter::class)->store(
        data: $generator(),
        path: 'exports/lazy.csv',
        disk: 'local',
        progress: function (int $row) use (&$events): void {
            $events[] = "progress:{$row}";
        }
    );

    expect($events)->toBe([
        'yield:1',
        'progress:1',
        'yield:2',
        'progress:2',
        'yield:3',
        'progress:3',
    ]);
});

it('handles a large generator', function (): void {
    config()->set('csv-export.utf8_bom', false);

    $generator = function (): Generator {
        for ($i = 1; $i <= 10000; $i++) {
            yield ['id' => $i, 'name' => "User {$i}"];
        }
    };

    $csv = app(CsvExporter::class)->toString($generator());

    expect($csv)
        ->toContain('1,"User 1"')
        ->toContain('10000,"User 10000"');
});
