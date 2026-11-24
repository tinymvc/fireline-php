<?php

namespace Spark\Fire\Console;

use Spark\Console\Prompt;
use Spark\Foundation\Console\StubCreation;
use Spark\Support\Str;
use function preg_match;
use function str_replace;
use function trim;

class MakeComponentCommand
{
    public function handle(array $args): void
    {
        $name = $args['_args'][0] ?? null;
        if (empty($name)) {
            $name = Prompt::ask('What is the name of the reactive component?');
        }

        $name = str_replace('\\', '/', trim((string) $name));
        if (!preg_match('#^[A-Za-z_][A-Za-z0-9_]*(/[A-Za-z_][A-Za-z0-9_]*)*$#D', $name)) {
            Prompt::error('Use a valid component class name, optionally prefixed with a namespace.');
            return;
        }

        $parts = explode('/', $name);
        $class = array_pop($parts);
        $view = implode('/', [...array_map('strtolower', $parts), Str::kebab($class)]);

        StubCreation::create($name, [
            'stub' => __DIR__ . '/stubs/component.stub',
            'destination' => 'app/Components/::subfolder:ucfirst/::name:ucfirst.php',
            'replacements' => [
                '{{ namespace }}' => 'App\Components::subfolder:namespace',
                '{{ class }}' => '::name:ucfirst',
                '{{ view }}' => 'components.' . str_replace('/', '.', $view),
            ],
        ]);

        StubCreation::create($name, [
            'stub' => __DIR__ . '/stubs/view.stub',
            'destination' => "resources/views/components/{$view}.blade.php",
            'replacements' => [
                '{{ $class }}' => '::name:ucfirst',
            ],
        ]);
    }
}
