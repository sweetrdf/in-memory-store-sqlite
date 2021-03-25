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

namespace sweetrdf\InMemoryStoreSqlite;

/**
 * This class provides helpers to handle RDF namespace related operations.
 */
final class NamespaceHelper
{
    const BASE_NAMESPACE = 'sweetrdf://in-memory-store-sqlite/';

    const NAMESPACE_RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    const NAMESPACE_XML = 'http://www.w3.org/XML/1998/namespace';
    const NAMESPACE_XSD = 'http://www.w3.org/2001/XMLSchema#';

    /**
     * List of known and new namespaces + prefixes.
     *
     * @var array<string,string>
     */
    private array $namespaces = [
        'owl:' => 'http://www.w3.org/2002/07/owl#',
        'rdf:' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs:' => 'http://www.w3.org/2000/01/rdf-schema#',
        'xsd:' => 'http://www.w3.org/2001/XMLSchema#',
    ];

    /**
     * @return array<string,string>
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * Get namespace by prefix.
     */
    public function getNamespace(string $prefix): ?string
    {
        foreach ($this->namespaces as $p => $ns) {
            if ($prefix == $p) {
                return $ns;
            }
        }

        return null;
    }

    /**
     * Get prefix by namespace.
     */
    public function getPrefix(string $namespace): ?string
    {
        foreach ($this->namespaces as $prefix => $ns) {
            if ($namespace == $ns) {
                return $prefix;
            }
        }

        return null;
    }

    public function hasPrefix(string $prefix): bool
    {
        return true === isset($this->namespaces[$prefix]);
    }

    public function setPrefix(string $prefix, string $namespace): void
    {
        $this->namespaces[$prefix] = $namespace;
    }
}
