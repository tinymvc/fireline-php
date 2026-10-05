<?php

namespace Spark\Fire;

use Spark\Http\Routing\Route;
use Spark\Foundation\Providers\ServiceProvider;
use Spark\Http\Routing\Router;

class FireServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Resolve the current request each time, including across test/worker requests.
        $this->app->bind(FireService::class);

        $this->app->get(Router::class)
            ->macro(
                'fire',
                fn(string $path, string $component, array $props = []) => new Route($path, method: 'GET', callback: fn() => Fire::render($component, $props))
            );
    }
}
