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

use Exception;
use sweetrdf\InMemoryStoreSqlite\Log\Logger;
use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;
use sweetrdf\InMemoryStoreSqlite\StringReader;

use function sweetrdf\InMemoryStoreSqlite\calcURI;

class TurtleParser extends BaseParser
{
    protected int $state;
    protected int $max_parsing_loops;
    protected string $unparsed_code;

    public function __construct(Logger $logger, NamespaceHelper $namespaceHelper, StringReader $stringReader)
    {
        parent::__construct($logger, $namespaceHelper, $stringReader);

        $this->state = 0;
        $this->unparsed_code = '';
        $this->max_parsing_loops = 500;
    }

    protected function x($re, $v, $options = 'si')
    {
        $v = preg_replace('/^[\xA0\xC2]+/', ' ', $v);

        /* comment removal */
        while (preg_match('/^\s*(\#[^\xd\xa]*)(.*)$/si', $v, $m)) {
            $v = $m[2];
        }

        return preg_match("/^\s*".$re.'(.*)$/'.$options, $v, $m) ? $m : false;
    }

    private function createBnodeID(): string
    {
        ++$this->bnode_id;

        return '_:'.$this->bnode_prefix.$this->bnode_id;
    }

    protected function addT(array $t): void
    {
        $this->triples[$this->t_count] = $t;
        ++$this->t_count;
    }

    protected function countTriples()
    {
        return $this->t_count;
    }

    protected function getUnparsedCode()
    {
        return $this->unparsed_code;
    }

    public function parse(string $path, string $data = ''): void
    {
        $this->triples = [];
        $this->t_count = 0;
        $this->reader->init($path, $data);
        $this->base = $this->reader->getBase();
        $this->r = ['vars' => []];
        /* parse */
        $buffer = '';
        $more_triples = [];
        $sub_v = '';
        $sub_v2 = '';
        $loops = 0;
        $prologue_done = 0;
        while ($d = $this->reader->readStream(8192)) {
            $buffer .= $d;
            $sub_v = $buffer;
            do {
                $proceed = 0;
                if (!$prologue_done) {
                    $proceed = 1;
                    if ((list($sub_r, $sub_v) = $this->xPrologue($sub_v)) && $sub_r) {
                        $loops = 0;
                        $sub_v .= $this->reader->readStream(128);
                        /* in case we missed the final DOT in the previous prologue loop */
                        if ($sub_r = $this->x('\.', $sub_v)) {
                            $sub_v = $sub_r[1];
                        }
                        /* more prologue to come, use outer loop */
                        if ($this->x("\@?(base|prefix)", $sub_v)) {
                            $proceed = 0;
                        }
                    } else {
                        $prologue_done = 1;
                    }
                }
                if (
                    $prologue_done
                    && (list($sub_r, $sub_v, $more_triples, $sub_v2) = $this->xTriplesBlock($sub_v))
                    && \is_array($sub_r)
                ) {
                    $proceed = 1;
                    $loops = 0;
                    foreach ($sub_r as $t) {
                        $this->addT($t);
                    }
                }
            } while ($proceed);
            ++$loops;
            $buffer = $sub_v;
            if ($loops > $this->max_parsing_loops) {
                $msg = 'too many loops: '.$loops.'. Could not parse "'.substr($buffer, 0, 200).'..."';
                throw new Exception($msg);
            }
        }
        foreach ($more_triples as $t) {
            $this->addT($t);
        }
        $sub_v = \count($more_triples) ? $sub_v2 : $sub_v;
        $buffer = $sub_v;
        $this->unparsed_code = $buffer;

        /* remove trailing comments */
        while (preg_match('/^\s*(\#[^\xd\xa]*)(.*)$/si', $this->unparsed_code, $m)) {
            $this->unparsed_code = $m[2];
        }

        if ($this->unparsed_code && !$this->logger->hasEntries('error')) {
            $rest = preg_replace('/[\x0a|\x0d]/i', ' ', substr($this->unparsed_code, 0, 30));
            if (trim($rest)) {
                $this->logger->error('Could not parse "'.$rest.'"');
            }
        }
    }

    protected function xPrologue($v)
    {
        $r = 0;
        if (!$this->t_count) {
            if ((list($sub_r, $v) = $this->xBaseDecl($v)) && $sub_r) {
                $this->base = $sub_r;
                $r = 1;
            }
            while ((list($sub_r, $v) = $this->xPrefixDecl($v)) && $sub_r) {
                $this->namespaceHelper->setPrefix($sub_r['prefix'], $sub_r['uri']);
                $r = 1;
            }
        }

        return [$r, $v];
    }

    /* 3 */

    protected function xBaseDecl($v)
    {
        if ($r = $this->x("\@?base\s+", $v)) {
            if ((list($r, $sub_v) = $this->xIRI_REF($r[1])) && $r) {
                if ($sub_r = $this->x('\.', $sub_v)) {
                    $sub_v = $sub_r[1];
                }

                return [$r, $sub_v];
            }
        }

        return [0, $v];
    }

    /* 4 */

    protected function xPrefixDecl($v)
    {
        if ($r = $this->x("\@?prefix\s+", $v)) {
            if ((list($r, $sub_v) = $this->xPNAME_NS($r[1])) && $r) {
                $prefix = $r;
                if ((list($r, $sub_v) = $this->xIRI_REF($sub_v)) && $r) {
                    $uri = calcURI($r, $this->base);
                    if ($sub_r = $this->x('\.', $sub_v)) {
                        $sub_v = $sub_r[1];
                    }

                    return [['prefix' => $prefix, 'uri_ref' => $r, 'uri' => $uri], $sub_v];
                }
            }
        }

        return [0, $v];
    }

    /* 21.., 32.. */

    protected function xTriplesBlock($v)
    {
        $pre_r = [];
        $r = [];
        $state = 1;
        $sub_v = $v;
        $buffer = $sub_v;
        do {
            $proceed = 0;
            if (1 == $state) {/* expecting subject */
                $t = ['type' => 'triple', 's' => '', 'p' => '', 'o' => '', 's_type' => '', 'p_type' => '', 'o_type' => '', 'o_datatype' => '', 'o_lang' => ''];
                if ((list($sub_r, $sub_v) = $this->xVarOrTerm($sub_v)) && $sub_r) {
                    $t['s'] = $sub_r['value'];
                    $t['s_type'] = $sub_r['type'];
                    $state = 2;
                    $proceed = 1;
                    if ($sub_r = $this->x('(\}|\.)', $sub_v)) {
                        if ('placeholder' == $t['s_type']) {
                            $state = 4;
                        } else {
                            $this->logger->error('"'.$sub_r[1].'" after subject found.');
                        }
                    }
                } elseif ((list($sub_r, $sub_v) = $this->xCollection($sub_v)) && $sub_r) {
                    $t['s'] = $sub_r['id'];
                    $t['s_type'] = $sub_r['type'];
                    $pre_r = array_merge($pre_r, $sub_r['triples']);
                    $state = 2;
                    $proceed = 1;
                    if ($sub_r = $this->x('\.', $sub_v)) {
                        $this->logger->error('DOT after subject found.');
                    }
                } elseif ((list($sub_r, $sub_v) = $this->xBlankNodePropertyList($sub_v)) && $sub_r) {
                    $t['s'] = $sub_r['id'];
                    $t['s_type'] = $sub_r['type'];
                    $pre_r = array_merge($pre_r, $sub_r['triples']);
                    $state = 2;
                    $proceed = 1;
                } elseif ($sub_r = $this->x('\.', $sub_v)) {
                    $this->logger->error('Subject expected, DOT found.'.$sub_v);
                }
            }
            if (2 == $state) {/* expecting predicate */
                if ($sub_r = $this->x('a\s+', $sub_v)) {
                    $sub_v = $sub_r[1];
                    $t['p'] = NamespaceHelper::NAMESPACE_RDF.'type';
                    $t['p_type'] = 'uri';
                    $state = 3;
                    $proceed = 1;
                } elseif ((list($sub_r, $sub_v) = $this->xVarOrTerm($sub_v)) && $sub_r) {
                    if ('bnode' == $sub_r['type']) {
                        $this->logger->error('Blank node used as triple predicate');
                    }
                    $t['p'] = $sub_r['value'];
                    $t['p_type'] = $sub_r['type'];
                    $state = 3;
                    $proceed = 1;
                } elseif ($sub_r = $this->x('\.', $sub_v)) {
                    $state = 4;
                } elseif ($sub_r = $this->x('\}', $sub_v)) {
                    $buffer = $sub_v;
                    $r = array_merge($r, $pre_r);
                    $pre_r = [];
                    $proceed = 0;
                }
            }
            if (3 == $state) {/* expecting object */
                if ((list($sub_r, $sub_v) = $this->xVarOrTerm($sub_v)) && $sub_r) {
                    $t['o'] = $sub_r['value'];
                    $t['o_type'] = $sub_r['type'];
                    $t['o_lang'] = $sub_r['lang'] ?? '';
                    $t['o_datatype'] = $sub_r['datatype'] ?? '';
                    $pre_r[] = $t;
                    $state = 4;
                    $proceed = 1;
                } elseif ((list($sub_r, $sub_v) = $this->xCollection($sub_v)) && $sub_r) {
                    $t['o'] = $sub_r['id'];
                    $t['o_type'] = $sub_r['type'];
                    $t['o_datatype'] = '';
                    $pre_r = array_merge($pre_r, [$t], $sub_r['triples']);
                    $state = 4;
                    $proceed = 1;
                } elseif ((list($sub_r, $sub_v) = $this->xBlankNodePropertyList($sub_v)) && $sub_r) {
                    $t['o'] = $sub_r['id'];
                    $t['o_type'] = $sub_r['type'];
                    $t['o_datatype'] = '';
                    $pre_r = array_merge($pre_r, [$t], $sub_r['triples']);
                    $state = 4;
                    $proceed = 1;
                }
            }
            if (4 == $state) {/* expecting . or ; or , or } */
                if ($sub_r = $this->x('\.', $sub_v)) {
                    $sub_v = $sub_r[1];
                    $buffer = $sub_v;
                    $r = array_merge($r, $pre_r);
                    $pre_r = [];
                    $state = 1;
                    $proceed = 1;
                } elseif ($sub_r = $this->x('\;', $sub_v)) {
                    $sub_v = $sub_r[1];
                    $state = 2;
                    $proceed = 1;
                } elseif ($sub_r = $this->x('\,', $sub_v)) {
                    $sub_v = $sub_r[1];
                    $state = 3;
                    $proceed = 1;
                    if ($sub_r = $this->x('\}', $sub_v)) {
                        $this->logger->error('Object expected, } found.');
                    }
                }
                if ($sub_r = $this->x('(\}|\{|OPTIONAL|FILTER|GRAPH)', $sub_v)) {
                    $buffer = $sub_v;
                    $r = array_merge($r, $pre_r);
                    $pre_r = [];
                    $proceed = 0;
                }
            }
        } while ($proceed);

        return \count($r) ? [$r, $buffer, $pre_r, $sub_v] : [0, $buffer, $pre_r, $sub_v];
    }

    /* 39.. */

    protected function xBlankNodePropertyList($v)
    {
        if ($sub_r = $this->x('\[', $v)) {
            $sub_v = $sub_r[1];
            $s = $this->createBnodeID();
            $r = ['id' => $s, 'type' => 'bnode', 'triples' => []];
            $t = ['type' => 'triple', 's' => $s, 'p' => '', 'o' => '', 's_type' => 'bnode', 'p_type' => '', 'o_type' => '', 'o_datatype' => '', 'o_lang' => ''];
            $state = 2;
            $closed = 0;
            do {
                $proceed = 0;
                if (2 == $state) {/* expecting predicate */
                    if ($sub_r = $this->x('a\s+', $sub_v)) {
                        $sub_v = $sub_r[1];
                        $t['p'] = NamespaceHelper::NAMESPACE_RDF.'type';
                        $t['p_type'] = 'uri';
                        $state = 3;
                        $proceed = 1;
                    } elseif ((list($sub_r, $sub_v) = $this->xVarOrTerm($sub_v)) && $sub_r) {
                        $t['p'] = $sub_r['value'];
                        $t['p_type'] = $sub_r['type'];
                        $state = 3;
                        $proceed = 1;
                    }
                }
                if (3 == $state) {/* expecting object */
                    if ((list($sub_r, $sub_v) = $this->xVarOrTerm($sub_v)) && $sub_r) {
                        $t['o'] = $sub_r['value'];
                        $t['o_type'] = $sub_r['type'];
                        $t['o_lang'] = $sub_r['lang'] ?? '';
                        $t['o_datatype'] = $sub_r['datatype'] ?? '';
                        $r['triples'][] = $t;
                        $state = 4;
                        $proceed = 1;
                    } elseif ((list($sub_r, $sub_v) = $this->xCollection($sub_v)) && $sub_r) {
                        $t['o'] = $sub_r['id'];
                        $t['o_type'] = $sub_r['type'];
                        $t['o_datatype'] = '';
                        $r['triples'] = array_merge($r['triples'], [$t], $sub_r['triples']);
                        $state = 4;
                        $proceed = 1;
                    } elseif ((list($sub_r, $sub_v) = $this->xBlankNodePropertyList($sub_v)) && $sub_r) {
                        $t['o'] = $sub_r['id'];
                        $t['o_type'] = $sub_r['type'];
                        $t['o_datatype'] = '';
                        $r['triples'] = array_merge($r['triples'], [$t], $sub_r['triples']);
                        $state = 4;
                        $proceed = 1;
                    }
                }
                if (4 == $state) {/* expecting . or ; or , or ] */
                    if ($sub_r = $this->x('\.', $sub_v)) {
                        $sub_v = $sub_r[1];
                        $state = 1;
                        $proceed = 1;
                    }
                    if ($sub_r = $this->x('\;', $sub_v)) {
                        $sub_v = $sub_r[1];
                        $state = 2;
                        $proceed = 1;
                    }
                    if ($sub_r = $this->x('\,', $sub_v)) {
                        $sub_v = $sub_r[1];
                        $state = 3;
                        $proceed = 1;
                    }
                    if ($sub_r = $this->x('\]', $sub_v)) {
                        $sub_v = $sub_r[1];
                        $proceed = 0;
                        $closed = 1;
                    }
                }
            } while ($proceed);
            if ($closed) {
                return [$r, $sub_v];
            }

            return [0, $v];
        }

        return [0, $v];
    }

    /* 40.. */

    protected function xCollection($v)
    {
        if ($sub_r = $this->x('\(', $v)) {
            $sub_v = $sub_r[1];
            $s = $this->createBnodeID();
            $r = ['id' => $s, 'type' => 'bnode', 'triples' => []];
            $closed = 0;
            do {
                $proceed = 0;
                if ((list($sub_r, $sub_v) = $this->xVarOrTerm($sub_v)) && $sub_r) {
                    $r['triples'][] = [
                        'type' => 'triple',
                        's' => $s,
                        's_type' => 'bnode',
                        'p' => NamespaceHelper::NAMESPACE_RDF.'first',
                        'p_type' => 'uri',
                        'o' => $sub_r['value'],
                        'o_type' => $sub_r['type'],
                        'o_lang' => $sub_r['lang'] ?? '',
                        'o_datatype' => $sub_r['datatype'] ?? '',
                    ];
                    $proceed = 1;
                } elseif ((list($sub_r, $sub_v) = $this->xCollection($sub_v)) && $sub_r) {
                    $r['triples'][] = [
                        'type' => 'triple',
                        's' => $s,
                        's_type' => 'bnode',
                        'p' => NamespaceHelper::NAMESPACE_RDF.'first',
                        'p_type' => 'uri',
                        'o' => $sub_r['id'],
                        'o_type' => $sub_r['type'],
                        'o_lang' => '',
                        'o_datatype' => '',
                    ];
                    $r['triples'] = array_merge($r['triples'], $sub_r['triples']);
                    $proceed = 1;
                } elseif ((list($sub_r, $sub_v) = $this->xBlankNodePropertyList($sub_v)) && $sub_r) {
                    $r['triples'][] = [
                        'type' => 'triple',
                        's' => $s,
                        'p' => NamespaceHelper::NAMESPACE_RDF.'first',
                        'o' => $sub_r['id'],
                        's_type' => 'bnode',
                        'p_type' => 'uri',
                        'o_type' => $sub_r['type'],
                        'o_lang' => '',
                        'o_datatype' => '',
                    ];
                    $r['triples'] = array_merge($r['triples'], $sub_r['triples']);
                    $proceed = 1;
                }
                if ($proceed) {
                    if ($sub_r = $this->x('\)', $sub_v)) {
                        $sub_v = $sub_r[1];
                        $r['triples'][] = [
                            'type' => 'triple',
                            's' => $s,
                            's_type' => 'bnode',
                            'p' => NamespaceHelper::NAMESPACE_RDF.'rest',
                            'p_type' => 'uri',
                            'o' => NamespaceHelper::NAMESPACE_RDF.'nil',
                            'o_type' => 'uri',
                            'o_lang' => '',
                            'o_datatype' => '',
                        ];
                        $closed = 1;
                        $proceed = 0;
                    } else {
                        $next_s = $this->createBnodeID();
                        $r['triples'][] = [
                            'type' => 'triple',
                            's' => $s,
                            'p' => NamespaceHelper::NAMESPACE_RDF.'rest',
                            'o' => $next_s,
                            's_type' => 'bnode',
                            'p_type' => 'uri',
                            'o_type' => 'bnode',
                            'o_lang' => '',
                            'o_datatype' => '',
                        ];
                        $s = $next_s;
                    }
                }
            } while ($proceed);
            if ($closed) {
                return [$r, $sub_v];
            }
        }

        return [0, $v];
    }

    /* 42 */

    protected function xVarOrTerm($v)
    {
        if ((list($sub_r, $sub_v) = $this->xVar($v)) && $sub_r) {
            return [$sub_r, $sub_v];
        } elseif ((list($sub_r, $sub_v) = $this->xGraphTerm($v)) && $sub_r) {
            return [$sub_r, $sub_v];
        }

        return [0, $v];
    }

    /* 44, 74.., 75.. */

    protected function xVar($v)
    {
        if ($r = $this->x('(\?|\$)([^\s]+)', $v)) {
            if ((list($sub_r, $sub_v) = $this->xVARNAME($r[2])) && $sub_r) {
                if (!\in_array($sub_r, $this->r['vars'])) {
                    $this->r['vars'][] = $sub_r;
                }

                return [['value' => $sub_r, 'type' => 'var'], $sub_v.$r[3]];
            }
        }

        return [0, $v];
    }

    /* 45 */

    protected function xGraphTerm($v)
    {
        foreach ([
            'IRIref' => 'uri',
            'RDFLiteral' => 'literal',
            'NumericLiteral' => 'literal',
            'BooleanLiteral' => 'literal',
            'BlankNode' => 'bnode',
            'NIL' => 'uri',
            'Placeholder' => 'placeholder',
        ] as $term => $type) {
            $m = 'x'.$term;
            if ((list($sub_r, $sub_v) = $this->$m($v)) && $sub_r) {
                if (!\is_array($sub_r)) {
                    $sub_r = ['value' => $sub_r];
                }
                $sub_r['type'] = $sub_r['type'] ?? $type;

                return [$sub_r, $sub_v];
            }
        }

        return [0, $v];
    }

    /* 60 */

    protected function xRDFLiteral($v)
    {
        if ((list($sub_r, $sub_v) = $this->xString($v)) && $sub_r) {
            $sub_r['value'] = $this->unescapeNtripleUTF($sub_r['value']);
            $r = $sub_r;
            if ((list($sub_r, $sub_v) = $this->xLANGTAG($sub_v)) && $sub_r) {
                $r['lang'] = $sub_r;
            } elseif (
                !$this->x('\s', $sub_v)
                && ($sub_r = $this->x('\^\^', $sub_v))
                && (list($sub_r, $sub_v) = $this->xIRIref($sub_r[1]))
                && $sub_r[1]
            ) {
                $r['datatype'] = $sub_r;
            }

            return [$r, $sub_v];
        }

        return [0, $v];
    }

    /* 61.., 62.., 63.., 64.. */

    protected function xNumericLiteral($v)
    {
        $sub_r = $this->x('(\-|\+)?', $v);
        $prefix = $sub_r[1];
        $sub_v = $sub_r[2];
        foreach (['DOUBLE' => 'double', 'DECIMAL' => 'decimal', 'INTEGER' => 'integer'] as $type => $xsd) {
            $m = 'x'.$type;
            if ((list($sub_r, $sub_v) = $this->$m($sub_v)) && (false !== $sub_r)) {
                $r = [
                    'value' => $prefix.$sub_r,
                    'type' => 'literal',
                    'datatype' => NamespaceHelper::NAMESPACE_XSD.$xsd,
                ];

                return [$r, $sub_v];
            }
        }

        return [0, $v];
    }

    /* 65.. */

    protected function xBooleanLiteral($v)
    {
        if ($r = $this->x('(true|false)', $v)) {
            return [$r[1], $r[2]];
        }

        return [0, $v];
    }

    /* 66.., 87.., 88.., 89.., 90.., 91.. */

    protected function xString($v)
    {/* largely simplified, may need some tweaks in following revisions */
        $sub_v = $v;
        if (!preg_match('/^\s*([\']{3}|\'|[\"]{3}|\")(.*)$/s', $sub_v, $m)) {
            return [0, $v];
        }
        $delim = $m[1];
        $rest = $m[2];
        $sub_types = ["'''" => 'literal_long1', '"""' => 'literal_long2', "'" => 'literal1', '"' => 'literal2'];
        $sub_type = $sub_types[$delim];
        $pos = 0;
        $r = false;
        do {
            $proceed = 0;
            $delim_pos = strpos($rest, $delim, $pos);
            if (false === $delim_pos) {
                break;
            }
            $new_rest = substr($rest, $delim_pos + \strlen($delim));
            $r = substr($rest, 0, $delim_pos);
            if (!preg_match('/([\x5c]+)$/s', $r, $m) || !(\strlen($m[1]) % 2)) {
                $rest = $new_rest;
            } else {
                $r = false;
                $pos = $delim_pos + 1;
                $proceed = 1;
            }
        } while ($proceed);
        if (false !== $r) {
            return [['value' => $r, 'type' => 'literal', 'sub_type' => $sub_type], $rest];
        }

        return [0, $v];
    }

    /* 67 */

    protected function xIRIref($v)
    {
        if ((list($r, $v) = $this->xIRI_REF($v)) && $r) {
            return [calcURI($r, $this->base), $v];
        } elseif ((list($r, $v) = $this->xPrefixedName($v)) && $r) {
            return [$r, $v];
        }

        return [0, $v];
    }

    /* 68 */

    protected function xPrefixedName($v)
    {
        if ((list($r, $v) = $this->xPNAME_LN($v)) && $r) {
            return [$r, $v];
        } elseif ((list($r, $sub_v) = $this->xPNAME_NS($v)) && $r) {
            return $this->namespaceHelper->hasPrefix($r)
                ? [$this->namespaceHelper->getNamespace($r), $sub_v]
                : [0, $v];
        }

        return [0, $v];
    }

    /* 69.., 73.., 93, 94..  */

    protected function xBlankNode($v)
    {
        if (($r = $this->x('\_\:', $v)) && (list($r, $sub_v) = $this->xPN_LOCAL($r[1])) && $r) {
            return [['type' => 'bnode', 'value' => '_:'.$r], $sub_v];
        }
        if ($r = $this->x('\[[\x20\x9\xd\xa]*\]', $v)) {
            return [['type' => 'bnode', 'value' => $this->createBnodeID()], $r[1]];
        }

        return [0, $v];
    }

    /* 70.. @@sync with SPARQLParser */

    protected function xIRI_REF($v)
    {
        //if ($r = $this->x('\<([^\<\>\"\{\}\|\^\'[:space:]]*)\>', $v)) {
        if (($r = $this->x('\<(\$\{[^\>]*\})\>', $v)) && ($sub_r = $this->xPlaceholder($r[1]))) {
            return [$r[1], $r[2]];
        } elseif ($r = $this->x('\<\>', $v)) {
            return [true, $r[1]];
        } elseif ($r = $this->x('\<([^\s][^\<\>]*)\>', $v)) {
            return [$r[1] ? $r[1] : true, $r[2]];
        }

        return [0, $v];
    }

    /* 71 */

    protected function xPNAME_NS($v)
    {
        list($r, $sub_v) = $this->xPN_PREFIX($v);
        $prefix = $r ?: '';

        return ($r = $this->x("\:", $sub_v)) ? [$prefix.':', $r[1]] : [0, $v];
    }

    /* 72 */

    protected function xPNAME_LN($v)
    {
        if ((list($r, $sub_v) = $this->xPNAME_NS($v)) && $r) {
            if (!$this->x('\s', $sub_v) && (list($sub_r, $sub_v) = $this->xPN_LOCAL($sub_v)) && $sub_r) {
                if (!$this->namespaceHelper->hasPrefix($r)) {
                    return [0, $v];
                }

                return [$this->namespaceHelper->getNamespace($r).$sub_r, $sub_v];
            }
        }

        return [0, $v];
    }

    /* 76 */

    protected function xLANGTAG($v)
    {
        if (!$this->x('\s', $v) && ($r = $this->x('\@([a-z]+(\-[a-z0-9]+)*)', $v))) {
            return [$r[1], $r[3]];
        }

        return [0, $v];
    }

    /* 77.. */

    protected function xINTEGER($v)
    {
        if ($r = $this->x('([0-9]+)', $v)) {
            return [$r[1], $r[2]];
        }

        return [false, $v];
    }

    /* 78.. */

    protected function xDECIMAL($v)
    {
        if ($r = $this->x('([0-9]+\.[0-9]*)', $v)) {
            return [$r[1], $r[2]];
        }
        if ($r = $this->x('(\.[0-9]+)', $v)) {
            return [$r[1], $r[2]];
        }

        return [false, $v];
    }

    /* 79.., 86.. */

    protected function xDOUBLE($v)
    {
        if ($r = $this->x('([0-9]+\.[0-9]*E[\+\-]?[0-9]+)', $v)) {
            return [$r[1], $r[2]];
        }
        if ($r = $this->x('(\.[0-9]+E[\+\-]?[0-9]+)', $v)) {
            return [$r[1], $r[2]];
        }
        if ($r = $this->x('([0-9]+E[\+\-]?[0-9]+)', $v)) {
            return [$r[1], $r[2]];
        }

        return [false, $v];
    }

    /* 92 */

    protected function xNIL($v)
    {
        if ($r = $this->x('\([\x20\x9\xd\xa]*\)', $v)) {
            return [['type' => 'uri', 'value' => NamespaceHelper::NAMESPACE_RDF.'nil'], $r[1]];
        }

        return [0, $v];
    }

    /* 95.. */

    protected function xPN_CHARS_BASE($v)
    {
        if ($r = $this->x("([a-z]+|\\\u[0-9a-f]{1,4})", $v)) {
            return [$r[1], $r[2]];
        }

        return [0, $v];
    }

    /* 96 */

    protected function xPN_CHARS_U($v)
    {
        if ((list($r, $sub_v) = $this->xPN_CHARS_BASE($v)) && $r) {
            return [$r, $sub_v];
        } elseif ($r = $this->x('(_)', $v)) {
            return [$r[1], $r[2]];
        }

        return [0, $v];
    }

    /* 97.. */

    protected function xVARNAME($v)
    {
        $r = '';
        do {
            $proceed = 0;
            if ($sub_r = $this->x('([0-9]+)', $v)) {
                $r .= $sub_r[1];
                $v = $sub_r[2];
                $proceed = 1;
            } elseif ((list($sub_r, $sub_v) = $this->xPN_CHARS_U($v)) && $sub_r) {
                $r .= $sub_r;
                $v = $sub_v;
                $proceed = 1;
            } elseif ($r && ($sub_r = $this->x('([\xb7\x300-\x36f]+)', $v))) {
                $r .= $sub_r[1];
                $v = $sub_r[2];
                $proceed = 1;
            }
        } while ($proceed);

        return [$r, $v];
    }

    /* 98.. */

    protected function xPN_CHARS($v)
    {
        if ((list($r, $sub_v) = $this->xPN_CHARS_U($v)) && $r) {
            return [$r, $sub_v];
        } elseif ($r = $this->x('([\-0-9\xb7\x300-\x36f])', $v)) {
            return [$r[1], $r[2]];
        }

        return [false, $v];
    }

    /* 99 */

    protected function xPN_PREFIX($v)
    {
        if ($sub_r = $this->x("([^\s\:\(\)\{\}\;\,]+)", $v, 's')) {/* accelerator */
            return [$sub_r[1], $sub_r[2]]; /* @@testing */
        }
        if ((list($r, $sub_v) = $this->xPN_CHARS_BASE($v)) && $r) {
            do {
                $proceed = 0;
                list($sub_r, $sub_v) = $this->xPN_CHARS($sub_v);
                if (false !== $sub_r) {
                    $r .= $sub_r;
                    $proceed = 1;
                } elseif ($sub_r = $this->x("\.", $sub_v)) {
                    $r .= '.';
                    $sub_v = $sub_r[1];
                    $proceed = 1;
                }
            } while ($proceed);
            list($sub_r, $sub_v) = $this->xPN_CHARS($sub_v);
            $r .= $sub_r ?: '';
        }

        return [$r, $sub_v];
    }

    /* 100 */

    protected function xPN_LOCAL($v)
    {
        if (($sub_r = $this->x("([^\s\(\)\{\}\[\]\;\,\.]+)", $v, 's')) && !preg_match('/^\./', $sub_r[2])) {/* accelerator */
            return [$sub_r[1], $sub_r[2]]; /* @@testing */
        }
        $r = '';
        $sub_v = $v;
        do {
            $proceed = 0;
            if ($this->x('\s', $sub_v)) {
                return [$r, $sub_v];
            }
            if ($sub_r = $this->x('([0-9])', $sub_v)) {
                $r .= $sub_r[1];
                $sub_v = $sub_r[2];
                $proceed = 1;
            } elseif ((list($sub_r, $sub_v) = $this->xPN_CHARS_U($sub_v)) && $sub_r) {
                $r .= $sub_r;
                $proceed = 1;
            } elseif ($r) {
                if (($sub_r = $this->x('(\.)', $sub_v)) && !preg_match('/^[\s\}]/s', $sub_r[2])) {
                    $r .= $sub_r[1];
                    $sub_v = $sub_r[2];
                }
                if ((list($sub_r, $sub_v) = $this->xPN_CHARS($sub_v)) && $sub_r) {
                    $r .= $sub_r;
                    $proceed = 1;
                }
            }
        } while ($proceed);

        return [$r, $sub_v];
    }

    protected function unescapeNtripleUTF($v)
    {
        if (false === strpos($v, '\\')) {
            return $v;
        }
        $mappings = ['t' => "\t", 'n' => "\n", 'r' => "\r", '\"' => '"', '\'' => "'"];
        foreach ($mappings as $in => $out) {
            $v = preg_replace('/\x5c(['.$in.'])/', $out, $v);
        }
        if (false === strpos(strtolower($v), '\u')) {
            return $v;
        }
        while (preg_match('/\\\(U)([0-9A-F]{8})/', $v, $m) || preg_match('/\\\(u)([0-9A-F]{4})/', $v, $m)) {
            $no = hexdec($m[2]);
            if ($no < 128) {
                $char = \chr($no);
            } elseif ($no < 2048) {
                $char = \chr(($no >> 6) + 192).\chr(($no & 63) + 128);
            } elseif ($no < 65536) {
                $char = \chr(($no >> 12) + 224).\chr((($no >> 6) & 63) + 128).\chr(($no & 63) + 128);
            } elseif ($no < 2097152) {
                $char = \chr(($no >> 18) + 240).\chr((($no >> 12) & 63) + 128).\chr((($no >> 6) & 63) + 128).\chr(($no & 63) + 128);
            } else {
                $char = '';
            }
            $v = str_replace('\\'.$m[1].$m[2], $char, $v);
        }

        return $v;
    }

    protected function xPlaceholder($v)
    {
        //if ($r = $this->x('(\?|\$)\{([^\}]+)\}', $v)) {
        if ($r = $this->x('(\?|\$)', $v)) {
            if (preg_match('/(\{(?:[^{}]+|(?R))*\})/', $r[2], $m) && 0 === strpos(trim($r[2]), $m[1])) {
                $ph = substr($m[1], 1, -1);
                $rest = substr(trim($r[2]), \strlen($m[1]));
                if (!isset($this->r['placeholders'])) {
                    $this->r['placeholders'] = [];
                }
                if (!\in_array($ph, $this->r['placeholders'])) {
                    $this->r['placeholders'][] = $ph;
                }

                return [['value' => $ph, 'type' => 'placeholder'], $rest];
            }
        }

        return [0, $v];
    }
}
