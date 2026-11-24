<?php

namespace Spark\Fire;

use ReflectionClass;
use RuntimeException;
use function config;
use function hash_equals;
use function hash_hmac;
use function is_string;
use function is_subclass_of;
use function method_exists;
use function str_starts_with;
use function strtolower;

final class ComponentSecurity
{
    public static function sign(string $id, string $class): string
    {
        $key = config('app.key');
        if (!is_string($key) || $key === '') {
            throw new RuntimeException('Set app.key before using reactive components.');
        }

        return hash_hmac('sha256', "{$id}:{$class}", $key);
    }

    public static function verify(string $id, string $class, ?string $checksum): bool
    {
        return $checksum !== null && $checksum !== '' && hash_equals(self::sign($id, $class), $checksum);
    }

    public static function isComponentClass(string $class): bool
    {
        return is_subclass_of($class, Component::class) && (new ReflectionClass($class))->isInstantiable();
    }

    /** Lifecycle hooks and framework methods are never browser actions. */
    public static function isMethodCallable(Component $component, string $method): bool
    {
        $name = strtolower($method);
        if (
            $name === '' || str_starts_with($name, '_') || method_exists(Component::class, $name)
            || $name === 'mount' || $name === 'view'
            || str_starts_with($name, 'updating') || str_starts_with($name, 'updated')
        ) {
            return false;
        }

        $reflection = new ReflectionClass($component);
        if (!$reflection->hasMethod($method)) {
            return false;
        }

        $action = $reflection->getMethod($method);
        return $action->isPublic() && !$action->isStatic();
    }
}
