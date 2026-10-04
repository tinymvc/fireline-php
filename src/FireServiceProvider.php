<?php

namespace Spark\Fire;

use Spark\Facades\Route;
use Spark\Foundation\Providers\ServiceProvider;
use Spark\Http\Routing\Router;

class FireServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FireService::class);

        $this->app->make(Router::class)
            ->macro(
                'fire',
                fn(string $path, string $component, array $props = []) => new Route($path, callback: fn() => Fire::render($component, $props))
            );
    }
}