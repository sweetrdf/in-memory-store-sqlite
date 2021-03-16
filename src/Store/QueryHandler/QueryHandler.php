<?php

/*
 * This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 * the terms of the GPL-3 license.
 *
 * (c) Konrad Abicht <hi@inspirito.de>
 * (c) Benjamin Nowack
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace sweetrdf\InMemoryStoreSqlite\Store\QueryHandler;

use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;
use sweetrdf\InMemoryStoreSqlite\Store\InMemoryStoreSqlite;

abstract class QueryHandler
{
    protected array $errors = [];

    protected InMemoryStoreSqlite $store;

    protected string $xsd = NamespaceHelper::NAMESPACE_XSD;

    public function __construct(InMemoryStoreSqlite $store)
    {
        $this->store = $store;
    }

    public function getTermID($val, $term = '')
    {
        return $this->store->getTermID($val, $term);
    }

    public function getValueHash($val)
    {
        return $this->store->getValueHash($val);
    }
}
