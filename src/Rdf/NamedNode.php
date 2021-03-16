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

use rdfInterface\NamedNode as iNamedNode;
use rdfInterface\Term;
use rdfInterface\TYPE_NAMED_NODE;

class NamedNode implements iNamedNode
{
    private string $iri;

    public function __construct(string $iri)
    {
        $this->iri = $iri;
    }

    public function __toString(): string
    {
        return '<'.$this->iri.'>';
    }

    public function getValue(): string
    {
        return $this->iri;
    }

    public function getType(): string
    {
        return TYPE_NAMED_NODE;
    }

    public function equals(Term $term): bool
    {
        return $this === $term;
    }
}
