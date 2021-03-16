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

use rdfInterface\BlankNode as iBlankNode;
use rdfInterface\Term;
use rdfInterface\TYPE_BLANK_NODE;

class BlankNode implements iBlankNode
{
    private string $id;

    public function __construct(?string $id = null)
    {
        if (empty($id)) {
            // if no ID was given, generate random unique string
            $id = bin2hex(random_bytes(16));
        }

        if (!str_starts_with($id, '_:')) {
            $id = '_:'.$id;
        }

        $this->id = $id;
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function equals(Term $term): bool
    {
        return $this === $term;
    }

    public function getType(): string
    {
        return TYPE_BLANK_NODE;
    }

    public function getValue(): string
    {
        return $this->id;
    }
}
