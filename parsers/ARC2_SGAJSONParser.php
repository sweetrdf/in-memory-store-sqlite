<?php

/*
 *  This file is part of the InMemoryStoreSqlite package and licensed under
 *  the terms of the GPL-3 license.
 *
 *  (c) Konrad Abicht <hi@inspirito.de>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

ARC2::inc('JSONParser');

class ARC2_SGAJSONParser extends ARC2_JSONParser
{
    public function __construct($a, &$caller)
    {
        parent::__construct($a, $caller);
    }

    public function __init()
    {/* reader */
        parent::__init();
        $this->rdf = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
        $this->nsp = [$this->rdf => 'rdf'];
    }

    public function done()
    {
        $this->extractRDF();
    }

    public function extractRDF($formats = '')
    {
        $s = $this->getContext();
        $os = $this->getURLs($this->struct);
        foreach ($os as $o) {
            if ($o != $s) {
                $this->addT($s, 'http://www.w3.org/2000/01/rdf-schema#seeAlso', $o, 'uri', 'uri');
            }
        }
    }

    public function getContext()
    {
        if (!isset($this->struct['canonical_mapping'])) {
            return '';
        }
        foreach ($this->struct['canonical_mapping'] as $k => $v) {
            return $v;
        }
    }

    public function getURLs($struct)
    {
        $r = [];
        if (is_array($struct)) {
            foreach ($struct as $k => $v) {
                if (preg_match('/^http:\/\//', $k) && !in_array($k, $r)) {
                    $r[] = $k;
                }
                $sub_r = $this->getURLs($v);
                foreach ($sub_r as $sub_v) {
                    if (!in_array($sub_v, $r)) {
                        $r[] = $sub_v;
                    }
                }
            }
        } elseif (preg_match('/^http:\/\//', $struct) && !in_array($struct, $r)) {
            $r[] = $struct;
        }

        return $r;
    }
}
