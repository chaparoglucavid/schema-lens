<?php

declare(strict_types=1);

namespace SchemaLens\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SchemaLens\Comparison\SchemaComparator;
use SchemaLens\Console\Commands\CompareCommand;
use SchemaLens\Console\Commands\MigrationCommand;
use SchemaLens\Contracts\SchemaComparatorInterface;
use SchemaLens\Contracts\SqlGeneratorInterface;
use SchemaLens\Database\SchemaInspectorFactory;
use SchemaLens\Migration\MigrationGenerator;
use SchemaLens\Services\SchemaComparisonService;
use SchemaLens\Sql\SqlGenerator;

final class SchemaLensServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/schemalens.php',
            'schemalens'
        );

        $this->app->singleton(SchemaInspectorFactory::class);
        $this->app->singleton(SchemaComparatorInterface::class, SchemaComparator::class);
        $this->app->singleton(SqlGeneratorInterface::class, SqlGenerator::class);
        $this->app->singleton(SqlGenerator::class);
        $this->app->singleton(MigrationGenerator::class);
        $this->app->singleton(SchemaComparisonService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/schemalens.php' => config_path('schemalens.php'),
            ], 'schemalens-config');

            $this->publishes([
                __DIR__.'/../../resources/views' => resource_path('views/vendor/schemalens'),
            ], 'schemalens-views');

            $this->publishes([
                __DIR__.'/../../resources/assets' => public_path('vendor/schemalens'),
            ], 'schemalens-assets');

            $this->commands([
                CompareCommand::class,
                MigrationCommand::class,
            ]);
        }

        if (! config('schemalens.enabled', true)) {
            return;
        }

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'schemalens');
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $prefix = config('schemalens.route_prefix', 'schema-lens');
        $middleware = config('schemalens.middleware', ['web', 'auth']);

        // Assets only need the web stack so CSS/JS can load without auth redirects.
        Route::middleware('web')
            ->prefix($prefix)
            ->group(function () {
                Route::get('/assets/css/schemalens.css', [\SchemaLens\Http\Controllers\AssetController::class, 'css'])
                    ->name('schemalens.assets.css');
                Route::get('/assets/js/schemalens.js', [\SchemaLens\Http\Controllers\AssetController::class, 'js'])
                    ->name('schemalens.assets.js');
            });

        Route::middleware($middleware)
            ->prefix($prefix)
            ->group(__DIR__.'/../../routes/web.php');
    }
}
