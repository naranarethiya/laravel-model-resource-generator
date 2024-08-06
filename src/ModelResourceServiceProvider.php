<?php

namespace Naranarethiya\ModelResourceGenerator;

use Illuminate\Support\ServiceProvider;
use Naranarethiya\ModelResourceGenerator\Console\Commands\GenerateApiResources;

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
