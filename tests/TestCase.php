<?php

declare(strict_types=1);

namespace IamAnkitThapar\LaravelCsvExport\Tests;

use IamAnkitThapar\LaravelCsvExport\CsvExportServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CsvExportServiceProvider::class,
        ];
    }
}