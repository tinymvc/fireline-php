<?php

namespace Spark\Fire\Console;

use Spark\Console\Prompt;
use Spark\Utils\File;
use function file_get_contents;
use function preg_replace;
use function str_replace;
use function strtolower;
use function trim;

class MakeComponentCommand
{
    public function handle(array $args): void
    {
        $name = $args['_args'][0] ?? null;

        if (empty($name)) {
            $name = Prompt::ask('What is the name of the reactive component?');
        }

        $name = trim((string) $name);
        $class = str_replace('/', '\\', $name);
        foreach (explode('\\', $class) as $part) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $part)) {
                Prompt::error('Use a valid component class name, optionally prefixed with a namespace.');
                return;
            }
        }

        $parts = explode('\\', $class);
        $className = array_pop($parts);
        $subNamespace = !empty($parts) ? '\\' . implode('\\', $parts) : '';
        $namespace = "App\\Components$subNamespace";

        $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className));
        $viewFolder = !empty($parts) ? strtolower(implode('/', $parts)) . '/' : '';
        $viewName = 'components.' . str_replace('/', '.', $viewFolder) . $kebab;

        $subPath = !empty($parts) ? implode('/', $parts) . '/' : '';
        $componentPath = "app/Components/{$subPath}{$className}.php";
        $viewPath = "resources/views/components/{$viewFolder}{$kebab}.blade.php";

        $classStub = file_get_contents(__DIR__ . '/stubs/component.stub');
        $classContent = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ view }}'],
            [$namespace, $className, $viewName],
            $classStub
        );

        File::ensureDirectoryWritable(dirname($componentPath));
        if (File::exists($componentPath)) {
            Prompt::warning("Component class already exists: {$componentPath}");
        } else {
            File::put($componentPath, $classContent);
            Prompt::success("Created Component: {$componentPath}");
        }

        $viewStub = file_get_contents(__DIR__ . '/stubs/view.stub');
        $viewContent = str_replace(['{{ $class }}'], [$className], $viewStub);

        File::ensureDirectoryWritable(dirname($viewPath));
        if (File::exists($viewPath)) {
            Prompt::warning("Component view already exists: {$viewPath}");
        } else {
            File::put($viewPath, $viewContent);
            Prompt::success("Created View: {$viewPath}");
        }
    }
}
