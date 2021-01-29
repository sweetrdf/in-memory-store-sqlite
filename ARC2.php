<?php
/**
 * ARC2 core class (static, not instantiated).
 *
 * @author Benjamin Nowack
 * @homepage <https://github.com/semsol/arc2>
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

    /**
     * @todo remove
     */
    public static function getFormat($v, $mtype = '', $ext = '')
    {
        $r = false;
        /* mtype check (atom, rdf/xml, turtle, n3, mp3, jpg) */
        $r = (!$r && preg_match('/\/atom\+xml/', $mtype)) ? 'atom' : $r;
        $r = (!$r && preg_match('/\/rdf\+xml/', $mtype)) ? 'rdfxml' : $r;
        $r = (!$r && preg_match('/\/(x\-)?turtle/', $mtype)) ? 'turtle' : $r;
        $r = (!$r && preg_match('/\/rdf\+n3/', $mtype)) ? 'n3' : $r;
        $r = (!$r && preg_match('/\/sparql-results\+xml/', $mtype)) ? 'sparqlxml' : $r;
        /* xml sniffing */
        if (
            !$r &&
            /* starts with angle brackets */
            preg_match('/^\s*\<[^\s]/s', $v) &&
            /* has an xmlns:* declaration or a matching pair of tags */
            (preg_match('/\sxmlns\:?/', $v) || preg_match('/\<([^\s]+).+\<\/\\1\>/s', $v)) // &&
        ) {
            while (preg_match('/^\s*\<\?xml[^\r\n]+\?\>\s*/s', $v)) {
                $v = preg_replace('/^\s*\<\?xml[^\r\n]+\?\>\s*/s', '', $v);
            }
            while (preg_match('/^\s*\<\!--.+?--\>\s*/s', $v)) {
                $v = preg_replace('/^\s*\<\!--.+?--\>\s*/s', '', $v);
            }
            /* doctype checks (html, rdf) */
            $r = (!$r && preg_match('/^\s*\<\!DOCTYPE\s+html[\s|\>]/is', $v)) ? 'html' : $r;
            $r = (!$r && preg_match('/^\s*\<\!DOCTYPE\s+[a-z0-9\_\-]\:RDF\s/is', $v)) ? 'rdfxml' : $r;
            /* markup checks */
            $v = preg_replace('/^\s*\<\!DOCTYPE\s.*\]\>/is', '', $v);
            $r = (!$r && preg_match('/^\s*\<rss\s+[^\>]*version/s', $v)) ? 'rss' : $r;
            $r = (!$r && preg_match('/^\s*\<feed\s+[^\>]+http\:\/\/www\.w3\.org\/2005\/Atom/s', $v)) ? 'atom' : $r;
            $r = (!$r && preg_match('/^\s*\<opml\s/s', $v)) ? 'opml' : $r;
            $r = (!$r && preg_match('/^\s*\<html[\s|\>]/is', $v)) ? 'html' : $r;
            $r = (!$r && preg_match('/^\s*\<sparql\s+[^\>]+http\:\/\/www\.w3\.org\/2005\/sparql\-results\#/s', $v)) ? 'sparqlxml' : $r;
            $r = (!$r && preg_match('/^\s*\<[^\>]+http\:\/\/www\.w3\.org\/2005\/sparql\-results#/s', $v)) ? 'srx' : $r;
            $r = (!$r && preg_match('/^\s*\<[^\s]*RDF[\s\>]/s', $v)) ? 'rdfxml' : $r;
            $r = (!$r && preg_match('/^\s*\<[^\>]+http\:\/\/www\.w3\.org\/1999\/02\/22\-rdf/s', $v)) ? 'rdfxml' : $r;

            $r = !$r ? 'xml' : $r;
        }
        /* json|jsonp */
        if (!$r && preg_match('/^[a-z0-9\.\(]*\s*[\{\[].*/s', trim($v))) {
            /* google social graph api */
            $r = (!$r && preg_match('/\"canonical_mapping\"/', $v)) ? 'sgajson' : $r;
            /* crunchbase api */
            $r = (!$r && preg_match('/\"permalink\"/', $v)) ? 'cbjson' : $r;

            $r = !$r ? 'json' : $r;
        }
        /* turtle/n3 */
        $r = (!$r && preg_match('/\@(prefix|base)/i', $v)) ? 'turtle' : $r;
        $r = (!$r && preg_match('/^(ttl)$/', $ext)) ? 'turtle' : $r;
        $r = (!$r && preg_match('/^(n3)$/', $ext)) ? 'n3' : $r;
        /* ntriples */
        $r = (!$r && preg_match('/^\s*(_:|<).+?\s+<[^>]+?>\s+\S.+?\s*\.\s*$/sm', $v)) ? 'ntriples' : $r;
        $r = (!$r && preg_match('/^(nt)$/', $ext)) ? 'ntriples' : $r;

        return $r;
    }

    /**
     * @todo remove
     */
    public static function getPreferredFormat($default = 'plain')
    {
        $formats = [
            'html' => 'HTML', 'text/html' => 'HTML', 'xhtml+xml' => 'HTML',
            'rdfxml' => 'RDFXML', 'rdf+xml' => 'RDFXML',
            'ntriples' => 'NTriples',
            'rdf+n3' => 'Turtle', 'x-turtle' => 'Turtle', 'turtle' => 'Turtle', 'text/turtle' => 'Turtle',
            'rdfjson' => 'RDFJSON', 'json' => 'RDFJSON',
            'xml' => 'XML',
            'legacyjson' => 'LegacyJSON',
        ];
        $prefs = [];
        $o_vals = [];
        /* accept header */
        $vals = explode(',', $_SERVER['HTTP_ACCEPT']);
        if ($vals) {
            foreach ($vals as $val) {
                if (preg_match('/(rdf\+n3|(x\-|text\/)turtle|rdf\+xml|text\/html|xhtml\+xml|xml|json)/', $val, $m)) {
                    $o_vals[$m[1]] = 1;
                    if (preg_match('/\;q\=([0-9\.]+)/', $val, $sub_m)) {
                        $o_vals[$m[1]] = 1 * $sub_m[1];
                    }
                }
            }
        }
        /* arg */
        if (isset($_GET['format'])) {
            $o_vals[$_GET['format']] = 1.1;
        }
        /* rank */
        arsort($o_vals);
        foreach ($o_vals as $val => $prio) {
            $prefs[] = $val;
        }
        /* default */
        $prefs[] = $default;
        foreach ($prefs as $pref) {
            if (isset($formats[$pref])) {
                return $formats[$pref];
            }
        }
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

    /* reader */

    public static function getReader($a = '')
    {
        return self::getComponent('Reader', $a);
    }

    /* parsers */

    public static function getParser($prefix, $a = '')
    {
        return self::getComponent($prefix.'Parser', $a);
    }

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
