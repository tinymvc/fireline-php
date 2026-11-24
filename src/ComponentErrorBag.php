<?php

namespace Spark\Fire;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;
use function count;

class ComponentErrorBag implements ArrayAccess, Countable, IteratorAggregate
{
    /** @param array<string, list<string>|string> $messages */
    public function __construct(protected array $messages = [])
    {
        $this->messages = array_map(static fn($message) => array_values((array) $message), $messages);
    }

    public function any(): bool
    {
        return !empty($this->messages);
    }

    public function isEmpty(): bool
    {
        return empty($this->messages);
    }

    public function isNotEmpty(): bool
    {
        return !empty($this->messages);
    }

    public function has(string $field): bool
    {
        return !empty($this->messages[$field]);
    }

    public function first(string $field): ?string
    {
        return $this->messages[$field][0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function get(string $field): array
    {
        return $this->messages[$field] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->messages;
    }

    public function toArray(): array
    {
        return $this->all();
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->all());
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): ?string
    {
        return $this->first((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->messages[(string) $offset] = array_values((array) $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->messages[(string) $offset]);
    }
}
