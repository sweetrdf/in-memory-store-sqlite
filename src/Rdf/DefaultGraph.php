<?php

namespace sweetrdf\InMemoryStoreSqlite\Rdf;

/*
 * This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 * the terms of the GPL-3 license.
 *
 * (c) Konrad Abicht <hi@inspirito.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use rdfInterface\DefaultGraph as iDefaultGraph;
use rdfInterface\Term;
use rdfInterface\TYPE_DEFAULT_GRAPH;
use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;

class DefaultGraph implements iDefaultGraph
{
    private ?string $iri;

    public function __construct(?string $iri = null)
    {
        if (empty($iri)) {
            $iri = NamespaceHelper::BASE_NAMESPACE;
        }

        $this->iri = $iri;
    }

    public function __toString(): string
    {
        return $this->getValue();
    }

    public function equals(Term $term): bool
    {
        return $this === $term;
    }

    public function getType(): string
    {
        return TYPE_DEFAULT_GRAPH;
    }

    public function getValue(): string
    {
        return $this->iri ?? TYPE_DEFAULT_GRAPH;
    }
}
