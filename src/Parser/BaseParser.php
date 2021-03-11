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

namespace sweetrdf\InMemoryStoreSqlite\Parser;

use ARC2_Class;
use ARC2_Reader;
use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;

class BaseParser extends ARC2_Class
{
    /**
     * @var array
     */
    protected $added_triples;

    /**
     * @var string
     */
    protected $base;

    /**
     * @var string
     */
    protected $bnode_id;

    /**
     * @var array
     */
    protected $blocks;

    /**
     * @var array<string, string>
     */
    protected $prefixes;

    /**
     * Query infos container.
     *
     * @var array
     */
    protected $r = [];

    /**
     * @var array
     */
    protected $triples = [];

    /**
     * @var int
     */
    protected $t_count = 0;

    public function __construct($a, &$caller)
    {
        parent::__construct($a, $caller);

        $this->reader = new ARC2_Reader($this->a, $this);

        /*
         * @todo make it a constructor param
         */
        $this->prefixes = (new NamespaceHelper())->getNamespaces();

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

    public function getSimpleIndex($flatten_objects = 1, $vals = ''): array
    {
        return $this->_getSimpleIndex($this->getTriples(), $flatten_objects, $vals);
    }

    /**
     * @todo port from ARC2::getSimpleIndex; refactor and merge it with $this->getSimpleIndex
     */
    private function _getSimpleIndex($triples, $flatten_objects = 1, $vals = ''): array
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
                if (!\in_array($o, $r[$s][$p])) {
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
                if (!\in_array($o, $r[$s][$p])) {
                    $r[$s][$p][] = $o;
                }
            }
        }

        return $r;
    }

    public function reset()
    {
        $this->__init();
        if (isset($this->reader)) {
            unset($this->reader);
        }
        if (isset($this->parser)) {
            $this->parser->__init();
            unset($this->parser);
        }
    }
}
