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

namespace sweetrdf\InMemoryStoreSqlite\Serializer;

use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;

use function sweetrdf\InMemoryStoreSqlite\splitURI;

class TurtleSerializer
{
    private array $ns = [];
    private array $nsp = [];
    private int $ns_count = 0;

    public function __construct()
    {
        $this->qualifier = ['rdf:type', 'rdfs:domain', 'rdfs:range', 'rdfs:subClassOf'];

        $rdf = NamespaceHelper::NAMESPACE_RDF;
        $this->nsp = [$rdf => 'rdf'];
        $this->used_ns = [$rdf];
        $this->ns = ['rdf' => $rdf];
    }

    public function getSerializedTriples($triples, $raw = 0)
    {
        $index = $this->getSimpleIndex($triples, 0);

        return $this->getSerializedIndex($index, $raw);
    }

    public function getSimpleIndex($triples, $flatten_objects = 1, $vals = '')
    {
        $r = [];
        foreach ($triples as $t) {
            $skip_t = 0;
            foreach (['s', 'p', 'o'] as $term) {
                $$term = $t[$term];
                /* template var */
                if (isset($t[$term.'_type']) && ('var' == $t[$term.'_type'])) {
                    $val = isset($vals[$$term]) ? $vals[$$term] : '';
                    $skip_t = isset($vals[$$term]) ? $skip_t : 1;
                    $type = '';
                    $type = !$type && isset($vals[$$term.' type']) ? $vals[$$term.' type'] : $type;
                    $type = !$type && preg_match('/^\_\:/', $val) ? 'bnode' : $type;
                    if ('o' == $term) {
                        $type = !$type && (preg_match('/\s/s', $val) || !preg_match('/\:/', $val)) ? 'literal' : $type;
                        $type = !$type && !preg_match('/[\/]/', $val) ? 'literal' : $type;
                    }
                    $type = !$type ? 'uri' : $type;
                    $t[$term.'_type'] = $type;
                    $$term = $val;
                }
            }
            if ($skip_t) {
                continue;
            }
            if (!isset($r[$s])) {
                $r[$s] = [];
            }
            if (!isset($r[$s][$p])) {
                $r[$s][$p] = [];
            }
            if ($flatten_objects) {
                if (!in_array($o, $r[$s][$p])) {
                    $r[$s][$p][] = $o;
                }
            } else {
                $o = ['value' => $o];
                foreach (['lang', 'type', 'datatype'] as $suffix) {
                    if (isset($t['o_'.$suffix]) && $t['o_'.$suffix]) {
                        $o[$suffix] = $t['o_'.$suffix];
                    } elseif (isset($t['o '.$suffix]) && $t['o '.$suffix]) {
                        $o[$suffix] = $t['o '.$suffix];
                    }
                }
                if (!in_array($o, $r[$s][$p])) {
                    $r[$s][$p][] = $o;
                }
            }
        }

        return $r;
    }

    /**
     * @todo port to NamespaceHelper
     */
    public function getPNameNamespace($v, $connector = ':')
    {
        $re = '/^([a-z0-9\_\-]+)\:([a-z0-9\_\-\.\%]+)$/i';
        if (':' != $connector) {
            $connectors = ['\:', '\-', '\_', '\.'];
            $chars = implode('', array_diff($connectors, [$connector]));
            $re = '/^([a-z0-9'.$chars.']+)\\'.$connector.'([a-z0-9\_\-\.\%]+)$/i';
        }
        if (!preg_match($re, $v, $m)) {
            return 0;
        }
        if (!isset($this->ns[$m[1]])) {
            return 0;
        }

        return $this->ns[$m[1]];
    }

    /**
     * @todo port to NamespaceHelper
     */
    public function getPName($v, $connector = ':')
    {
        /* is already a pname */
        $ns = $this->getPNameNamespace($v, $connector);
        if ($ns) {
            if (!in_array($ns, $this->used_ns)) {
                $this->used_ns[] = $ns;
            }

            return $v;
        }
        /* new pname */
        $parts = splitURI($v);
        if ($parts) {
            /* known prefix */
            foreach ($this->ns as $prefix => $ns) {
                if ($parts[0] == $ns) {
                    if (!in_array($ns, $this->used_ns)) {
                        $this->used_ns[] = $ns;
                    }

                    return $prefix.$connector.$parts[1];
                }
            }
            /* new prefix */
            $prefix = $this->getPrefix($parts[0]);

            return $prefix.$connector.$parts[1];
        }

        return $v;
    }

    /**
     * @todo port to NamespaceHelper
     */
    public function getPrefix($ns)
    {
        if (!isset($this->nsp[$ns])) {
            $this->ns['ns'.$this->ns_count] = $ns;
            $this->nsp[$ns] = 'ns'.$this->ns_count;
            ++$this->ns_count;
        }
        if (!in_array($ns, $this->used_ns)) {
            $this->used_ns[] = $ns;
        }

        return $this->nsp[$ns];
    }

    public function getTerm($v, $term = '', $qualifier = '')
    {
        if (!\is_array($v)) {
            if (preg_match('/^\_\:/', $v)) {
                return $v;
            }
            if (('p' === $term) && ($pn = $this->getPName($v))) {
                return $pn;
            }
            if (
                ('o' === $term)
                && \in_array($qualifier, $this->qualifier)
                && ($pn = $this->getPName($v))
            ) {
                return $pn;
            }
            if (preg_match('/^[a-z0-9]+\:[^\s]*$/isu', $v)) {
                return '<'.$v.'>';
            }

            return $this->getTerm(['type' => 'literal', 'value' => $v], $term, $qualifier);
        }
        if (!isset($v['type']) || ('literal' != $v['type'])) {
            return $this->getTerm($v['value'], $term, $qualifier);
        }
        /* literal */
        $quot = '"';
        if (false !== preg_match('/\"/', $v['value'])) {
            $quot = "'";
            if (false !== preg_match('/\'/', $v['value']) || false !== preg_match('/[\x0d\x0a]/', $v['value'])) {
                $quot = '"""';
                if (
                    false !== preg_match('/\"\"\"/', $v['value'])
                    || false !== preg_match('/\"$/', $v['value'])
                    || false !== preg_match('/^\"/', $v['value'])
                ) {
                    $quot = "'''";
                    $v['value'] = preg_replace("/'$/", "' ", $v['value']);
                    $v['value'] = preg_replace("/^'/", " '", $v['value']);
                    $v['value'] = str_replace("'''", '\\\'\\\'\\\'', $v['value']);
                }
            }
        }
        if ((1 == \strlen($quot)) && false !== preg_match('/[\x0d\x0a]/', $v['value'])) {
            $quot = $quot.$quot.$quot;
        }
        $suffix = isset($v['lang']) && $v['lang'] ? '@'.$v['lang'] : '';
        $suffix = isset($v['datatype']) && $v['datatype'] ? '^^'.$this->getTerm($v['datatype'], 'dt') : $suffix;

        return $quot.$v['value'].$quot.$suffix;
    }

    public function getHead()
    {
        $r = '';
        $nl = "\n";
        foreach ($this->used_ns as $v) {
            $r .= $r ? $nl : '';
            foreach ($this->ns as $prefix => $ns) {
                if ($ns != $v) {
                    continue;
                }
                $r .= '@prefix '.$prefix.': <'.$v.'> .';
                break;
            }
        }

        return $r;
    }

    public function getSerializedIndex($index, $raw = 0)
    {
        $r = '';
        $nl = "\n";
        foreach ($index as $s => $ps) {
            $r .= $r ? ' .'.$nl.$nl : '';
            $s = $this->getTerm($s, 's');
            $r .= $s;
            $first_p = 1;
            foreach ($ps as $p => $os) {
                if (!$os) {
                    continue;
                }
                $p = $this->getTerm($p, 'p');
                $r .= $first_p ? ' ' : ' ;'.$nl.str_pad('', \strlen($s) + 1);
                $r .= $p;
                $first_o = 1;
                if (!\is_array($os)) {/* single literal o */
                    $os = [['value' => $os, 'type' => 'literal']];
                }
                foreach ($os as $o) {
                    $r .= $first_o ? ' ' : ' ,'.$nl.str_pad('', \strlen($s) + \strlen($p) + 2);
                    $o = $this->getTerm($o, 'o', $p);
                    $r .= $o;
                    $first_o = 0;
                }
                $first_p = 0;
            }
        }
        $r .= $r ? ' .' : '';
        if ($raw) {
            return $r;
        }

        return $r ? $this->getHead().$nl.$nl.$r : '';
    }
}
