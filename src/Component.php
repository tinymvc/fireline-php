<?php

namespace Spark\Fire;

use BadMethodCallException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Spark\Foundation\Exceptions\ValidationException;
use Spark\Http\Validator;
use Spark\View\Blade;
use function filter_var;
use function in_array;
use function is_array;
use function is_scalar;
use function method_exists;
use function str_replace;
use function str_starts_with;
use function ucwords;
use const FILTER_VALIDATE_BOOLEAN;

abstract class Component
{
    protected ?string $id = null;

    /** @var array<string, string> Event names mapped to action methods. */
    protected array $listeners = [];

    private array $dispatched = [];

    protected ?ComponentErrorBag $errors = null;

    protected ?string $redirectUrl = null;

    protected ?string $navigateUrl = null;

    /** Lifecycle hook: executed on every instantiation. */
    public function boot(): void
    {
    }

    /** Lifecycle hook: executed before action/update processing. */
    public function hydrate(): void
    {
    }

    /** Lifecycle hook: executed after action/update processing before rendering. */
    public function dehydrate(): void
    {
    }

    /** Hook called before any property is updated. */
    public function updating(string $property, mixed $value): void
    {
    }

    /** Hook called after any property is updated. */
    public function updated(string $property, mixed $value): void
    {
    }

    /**
     * Render the component view.
     * Can return rendered HTML, a Blade instance, or delegates to view().
     */
    public function render(): string|Blade
    {
        if (method_exists($this, 'view')) {
            $view = $this->view();
            $context = [
                ...$this->getState(),
                'component' => $this,
                'errors' => $this->getErrorBag(),
            ];

            return app(Blade::class)->render($view, $context);
        }

        throw new BadMethodCallException(static::class . ' must implement render() or view().');
    }

    public function getId(): string
    {
        return $this->id ??= bin2hex(random_bytes(16));
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /** Dispatch an event to the browser. */
    public function dispatch(string $event, mixed ...$params): void
    {
        $this->dispatched[] = [
            'name' => $event,
            'params' => $params,
        ];
    }

    /** Retrieve events dispatched during this request. */
    public function getDispatched(): array
    {
        return $this->dispatched;
    }

    /** Get declared event listeners. */
    public function getListeners(): array
    {
        return $this->listeners;
    }

    /** Redirect to the specified URL (browser reload/redirect). */
    public function redirect(string $url): void
    {
        $this->redirectUrl = $url;
    }

    /** Navigate to the specified URL via FireLine client router. */
    public function navigate(string $url): void
    {
        $this->navigateUrl = $url;
    }

    public function getRedirect(): ?string
    {
        return $this->redirectUrl;
    }

    public function getNavigate(): ?string
    {
        return $this->navigateUrl;
    }

    /** Get all public, non-static properties representing component state. */
    public function getState(): array
    {
        $state = [];
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            if ($property->isStatic() || $property->isReadOnly() || str_starts_with($property->getName(), '_')) {
                continue;
            }
            if ($property->isInitialized($this)) {
                $state[$property->getName()] = $property->getValue($this);
            }
        }

        return $state;
    }

    /** Fill component public properties from an array with type coercion. */
    public function fill(array $state): void
    {
        $reflection = new ReflectionClass($this);
        foreach ($state as $key => $value) {
            if (!$reflection->hasProperty($key)) {
                continue;
            }

            $property = $reflection->getProperty($key);
            if (!$property->isPublic() || $property->isStatic() || $property->isReadOnly() || str_starts_with($key, '_')) {
                continue;
            }

            $this->$key = $this->coerceValue($property, $value);
        }
    }

    /** Set a single property value through its full updating/updated lifecycle hooks. */
    public function syncProperty(string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($this);
        if (!$reflection->hasProperty($property)) {
            return;
        }

        $prop = $reflection->getProperty($property);
        if (!$prop->isPublic() || $prop->isStatic() || $prop->isReadOnly() || str_starts_with($property, '_')) {
            return;
        }

        $coerced = $this->coerceValue($prop, $value);
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $property)));
        $updatingMethod = "updating{$studly}";
        $updatedMethod = "updated{$studly}";

        if (method_exists($this, $updatingMethod)) {
            $this->$updatingMethod($coerced);
        }
        $this->updating($property, $coerced);

        $this->$property = $coerced;

        $this->updated($property, $coerced);
        if (method_exists($this, $updatedMethod)) {
            $this->$updatedMethod($coerced);
        }
    }

    /**
     * Validate component state using TinyCore Validator rules.
     *
     * @throws ValidationException
     */
    public function validate(array $rules): array
    {
        $validator = Validator::make($rules, $this->getState());
        $errors = $validator->errors();

        if (!empty($errors)) {
            $this->setErrors($errors);
            throw ValidationException::make('Validation failed.', $errors);
        }

        $this->errors = new ComponentErrorBag();
        return $validator->validated()->all();
    }

    public function addError(string $field, string $message): void
    {
        $this->getErrorBag()->offsetSet($field, [$message]);
    }

    public function resetErrors(): void
    {
        $this->errors = new ComponentErrorBag();
    }

    public function getErrorBag(): ComponentErrorBag
    {
        return $this->errors ??= new ComponentErrorBag();
    }

    public function setErrors(array $errors): void
    {
        $this->errors = new ComponentErrorBag($errors);
    }

    private function coerceValue(ReflectionProperty $property, mixed $value): mixed
    {
        $type = $property->getType();
        if ($value === null) {
            if ($type !== null && !$type->allowsNull()) {
                throw new InvalidArgumentException("Property '{$property->getName()}' cannot be null.");
            }
            return null;
        }

        if (!($type instanceof ReflectionNamedType)) {
            return $value;
        }

        $name = $type->getName();
        if (in_array($name, ['int', 'float', 'bool', 'string'], true) && !is_scalar($value)) {
            throw new InvalidArgumentException("Property '{$property->getName()}' requires a scalar value.");
        }
        if ($name === 'array' && !is_array($value)) {
            throw new InvalidArgumentException("Property '{$property->getName()}' requires an array.");
        }

        return match ($name) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $value,
            'array' => (array) $value,
            default => $value,
        };
    }
}
