<?php

namespace sweetrdf\InMemoryStoreSqlite\Log;

use Exception;

final class Logger
{
    /**
     * @var array<string,array<integer,mixed>>
     */
    private array $entries = [];

    public function __construct()
    {
        $this->entries = [
            'error' => [],
            'warning' => [],
        ];
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    private function log($level, $message, array $context = []): void
    {
        if (!isset($this->entries[$level])) {
            $this->entries[$level] = [];
        }

        $this->entries[$level][] = ['message' => $message, 'context' => $context];
    }

    public function getEntries(?string $level = null): array
    {
        if (null !== $level && isset($this->entries[$level])) {
            return $this->entries[$level];
        } elseif (null == $level) {
            return $this->entries;
        }

        throw new Exception('Level '.$level.' not set.');
    }

    public function hasEntries(?string $level = null): bool
    {
        return 0 < \count($this->getEntries($level));
    }
}
