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

use ARC2;
use ARC2_Class;

class TurtleSerializer extends ARC2_Class
{
    public function __construct($a, &$caller)
    {
        parent::__construct($a, $caller);

        $this->qualifier = ['rdf:type', 'rdfs:domain', 'rdfs:range', 'rdfs:subClassOf'];
    }

    public function getSerializedTriples($triples, $raw = 0)
    {
        $index = ARC2::getSimpleIndex($triples, 0);

        return $this->getSerializedIndex($index, $raw);
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
