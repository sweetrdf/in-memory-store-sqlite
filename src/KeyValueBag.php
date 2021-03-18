<?php

namespace sweetrdf\InMemoryStoreSqlite;

/**
 * This class acts as a simple key-value cache to speed up insert into operations.
 */
final class KeyValueBag
{
    private array $bag = [];

    public function set(string $key, array $value): void
    {
        $this->bag[$key] = $value;
    }

    public function get(string $key): array | null
    {
        return $this->bag[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return null !== $this->get($key);
    }

    public function hasEntries(): bool
    {
        return 0 < \count($this->bag);
    }

    public function reset(): void
    {
        $this->bag = [];
    }
}
