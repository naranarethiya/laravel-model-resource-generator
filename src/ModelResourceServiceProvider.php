<?php

namespace Naranarethiya\ModelResourceGenerator;

use App\Console\Commands\GenerateApiResources;
use Illuminate\Support\ServiceProvider;

class ModelResourceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            GenerateApiResources::class,
        ]);
    }

    public function boot(): void {}
}
