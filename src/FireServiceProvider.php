<?php

namespace Spark\Fire;

use Spark\Console\Commands;
use Spark\Fire\Console\MakeComponentCommand;
use Spark\Foundation\Providers\ServiceProvider;
use Spark\Http\Middleware;
use Spark\Http\Routing\Route;
use Spark\Http\Routing\Router;
use Spark\View\Blade;

class FireServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Resolve the current request each time, including across test/worker requests.
        $this->app->bind(FireService::class);
        $this->app->bind(ComponentController::class);

        $router = $this->app->get(Router::class);

        $router->macro(
            'fire',
            fn(string $path, string $component, array $props = []) => new Route($path, method: 'GET', callback: fn() => Fire::render($component, $props))
        );

        $router->post('/_fire/component', [ComponentController::class, 'handle'])
            ->name('fire.component');
    }

    public function boot(): void
    {
        $this->app->get(Middleware::class)->queue(FireMiddleware::class);
        $this->app->get(Blade::class)->directive(
            'reactive',
            static fn(string $expression) => "<?php echo \\Spark\\Fire\\Fire::component({$expression}); ?>"
        );

        if (is_cli()) {
            $this->app->get(Commands::class)
                ->addCommand('make:fire', [MakeComponentCommand::class, 'handle'], 'Create a reactive FireLine component and Blade view');
        }
    }
}
