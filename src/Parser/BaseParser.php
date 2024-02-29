<?php

/**
 * This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 * the terms of the GPL-2 license.
 *
 * (c) Konrad Abicht <hi@inspirito.de>
 * (c) Benjamin Nowack
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace sweetrdf\InMemoryStoreSqlite\Parser;

use sweetrdf\InMemoryStoreSqlite\Log\Logger;
use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;
use sweetrdf\InMemoryStoreSqlite\StringReader;

abstract class BaseParser
{
    /**
     * @var array
     */
    protected $added_triples;

    protected string $base;

    protected string $bnode_prefix;

    protected string $bnode_id;

    protected array $blocks;

    protected Logger $logger;

    protected NamespaceHelper $namespaceHelper;

    /**
     * Query infos container.
     */
    protected array $r = [];

    protected StringReader $reader;

    protected array $triples = [];

    protected int $t_count = 0;

    public function __construct(Logger $logger, NamespaceHelper $namespaceHelper, StringReader $stringReader)
    {
        $this->reader = $stringReader;

        $this->logger = $logger;

        $this->namespaceHelper = $namespaceHelper;

        // generates random prefix for blank nodes
        $this->bnode_prefix = bin2hex(random_bytes(4)).'b';

        $this->bnode_id = 0;
    }

    public function getQueryInfos()
    {
        return $this->r;
    }

    public function getTriples()
    {
        return $this->triples;
    }
}
