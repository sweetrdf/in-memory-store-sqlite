<?php

namespace sweetrdf\InMemoryStoreSqlite\Log;

use Exception;

final class LoggerPool
{
    /**
     * @var array<int,\sweetrdf\InMemoryStoreSqlite\Log\Logger>
     */
    private array $logger = [];

    public function createNewLogger(string $id): Logger
    {
        $this->logger[$id] = new Logger();
        return $this->logger[$id];
    }

    public function getLogger(string $id): Logger
    {
        if (isset($this->logger[$id])) {
            return $this->logger[$id];
        }

        throw new Exception('Invalid ID given.');
    }

    public function getEntriesFromAllLoggerInstances(?string $level = null): iterable
    {
        $result = [];

        foreach ($this->logger as $logger) {
            $result = array_merge($result, $logger->getEntries($level));
        }

        return $result;
    }

    public function hasEntriesInAnyLoggerInstance(?string $level = null): bool
    {
        foreach ($this->logger as $logger) {
            if ($logger->hasEntries($level)) {
                return true;
            }
        }

        return false;
    }
}
