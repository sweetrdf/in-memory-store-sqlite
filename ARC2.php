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

/**
 * @deprecated dont rely on this class, because it gets removed in the future
 */
class ARC2
{
    public static function x($re, $v, $options = 'si')
    {
        return preg_match("/^\s*".$re.'(.*)$/'.$options, $v, $m) ? $m : false;
    }

    public static function splitURI($v)
    {
        /* the following namespaces may lead to conflated URIs,
         * we have to set the split position manually
        */
        if (strpos($v, 'www.w3.org')) {
            $specials = [
                'http://www.w3.org/XML/1998/namespace',
                'http://www.w3.org/2005/Atom',
                'http://www.w3.org/1999/xhtml',
            ];
            foreach ($specials as $ns) {
                if (str_contains($v, $ns)) {
                    $local_part = substr($v, strlen($ns));
                    if (!preg_match('/^[\/\#]/', $local_part)) {
                        return [$ns, $local_part];
                    }
                }
            }
        }
        /* auto-splitting on / or # */
        //$re = '^(.*?)([A-Z_a-z][-A-Z_a-z0-9.]*)$';
        if (preg_match('/^(.*[\/\#])([^\/\#]+)$/', $v, $m)) {
            return [$m[1], $m[2]];
        }
        /* auto-splitting on last special char, e.g. urn:foo:bar */
        if (preg_match('/^(.*[\:\/])([^\:\/]+)$/', $v, $m)) {
            return [$m[1], $m[2]];
        }

        return [$v, ''];
    }

    public static function getSimpleIndex($triples, $flatten_objects = 1, $vals = '')
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

    public static function getComponent($name, $a = '', $caller = '')
    {
        $prefix = 'ARC2';
        if (preg_match('/^([^\_]+)\_(.+)$/', $name, $m)) {
            $prefix = $m[1];
            $name = $m[2];
        }
        $cls = $prefix.'_'.$name;
        if (!$caller) {
            $caller = new stdClass();
        }

        return new $cls($a, $caller);
    }

    /* parsers */

    public static function getParser($prefix, $a = '')
    {
        return self::getComponent($prefix.'Parser', $a);
    }

    /**
     * @todo only for test purposes; use hardf instead and remove this
     */
    public static function getTurtleParser($a = '')
    {
        return self::getParser('Turtle', $a);
    }

    /* store */

    public static function getStore($a = '', $caller = '')
    {
        return self::getComponent('Store', [], $caller);
    }
}
