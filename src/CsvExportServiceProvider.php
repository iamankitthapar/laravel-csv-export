<?php

declare(strict_types=1);

namespace IamAnkitThapar\LaravelCsvExport;

use Illuminate\Support\ServiceProvider;

class CsvExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/csv-export.php',
            'csv-export'
        );

        $this->app->singleton(
            CsvExporter::class,
            fn (): CsvExporter => new CsvExporter()
        );

        $this->app->alias(CsvExporter::class, 'csv-export');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/csv-export.php'
                => config_path('csv-export.php'),
        ], 'csv-export-config');
    }
}