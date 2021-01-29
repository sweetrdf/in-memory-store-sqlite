<?php
/**
 * ARC2 core class (static, not instantiated).
 *
 * @author Benjamin Nowack
 * @homepage <https://github.com/semsol/arc2>
 */

/* E_STRICT hack */
if (function_exists('date_default_timezone_get')) {
    date_default_timezone_set(date_default_timezone_get());
}

/**
 * @deprecated dont rely on this class, because it gets removed in the future
 */
class ARC2
{
    public static function mtime()
    {
        return microtime(true);
    }

    public static function x($re, $v, $options = 'si')
    {
        return preg_match("/^\s*".$re.'(.*)$/'.$options, $v, $m) ? $m : false;
    }

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

    public static function getTriplesFromIndex($index)
    {
        $r = [];
        foreach ($index as $s => $ps) {
            foreach ($ps as $p => $os) {
                foreach ($os as $o) {
                    $r[] = [
                        's' => $s,
                        'p' => $p,
                        'o' => $o['value'],
                        's_type' => preg_match('/^\_\:/', $s) ? 'bnode' : 'uri',
                        'o_type' => $o['type'],
                        'o_datatype' => isset($o['datatype']) ? $o['datatype'] : '',
                        'o_lang' => isset($o['lang']) ? $o['lang'] : '',
                    ];
                }
            }
        }

        return $r;
    }

    public static function getMergedIndex()
    {
        $r = [];
        foreach (func_get_args() as $index) {
            foreach ($index as $s => $ps) {
                if (!isset($r[$s])) {
                    $r[$s] = [];
                }
                foreach ($ps as $p => $os) {
                    if (!isset($r[$s][$p])) {
                        $r[$s][$p] = [];
                    }
                    foreach ($os as $o) {
                        if (!in_array($o, $r[$s][$p])) {
                            $r[$s][$p][] = $o;
                        }
                    }
                }
            }
        }

        return $r;
    }

    public static function getCleanedIndex()
    {/* removes triples from a given index */
        $indexes = func_get_args();
        $r = $indexes[0];
        for ($i = 1, $i_max = count($indexes); $i < $i_max; ++$i) {
            $index = $indexes[$i];
            foreach ($index as $s => $ps) {
                if (!isset($r[$s])) {
                    continue;
                }
                foreach ($ps as $p => $os) {
                    if (!isset($r[$s][$p])) {
                        continue;
                    }
                    $r_os = $r[$s][$p];
                    $new_os = [];
                    foreach ($r_os as $r_o) {
                        $r_o_val = is_array($r_o) ? $r_o['value'] : $r_o;
                        $keep = 1;
                        foreach ($os as $o) {
                            $del_o_val = is_array($o) ? $o['value'] : $o;
                            if ($del_o_val == $r_o_val) {
                                $keep = 0;
                                break;
                            }
                        }
                        if ($keep) {
                            $new_os[] = $r_o;
                        }
                    }
                    if ($new_os) {
                        $r[$s][$p] = $new_os;
                    } else {
                        unset($r[$s][$p]);
                    }
                }
            }
        }
        /* check r */
        $has_data = 0;
        foreach ($r as $s => $ps) {
            if ($ps) {
                $has_data = 1;
                break;
            }
        }

        return $has_data ? $r : [];
    }

    public static function getStructType($v)
    {
        /* string */
        if (is_string($v)) {
            return 'string';
        }
        /* flat array, numeric keys */
        if (in_array(0, array_keys($v))) {/* numeric keys */
            /* simple array */
            if (!is_array($v[0])) {
                return 'array';
            }
            /* triples */
            //if (isset($v[0]) && isset($v[0]['s']) && isset($v[0]['p'])) return 'triples';
            if (in_array('p', array_keys($v[0]))) {
                return 'triples';
            }
        }
        /* associative array */
        else {
            /* index */
            foreach ($v as $s => $ps) {
                if (!is_array($ps)) {
                    break;
                }
                foreach ($ps as $p => $os) {
                    if (!is_array($os) || !is_array($os[0])) {
                        break;
                    }
                    if (in_array('value', array_keys($os[0]))) {
                        return 'index';
                    }
                }
            }
        }
        /* array */
        return 'array';
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

    /* graph */

    public static function getGraph($a = '')
    {
        return self::getComponent('Graph', $a);
    }

    /* resource */

    public static function getResource($a = '')
    {
        return self::getComponent('Resource', $a);
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

    public static function getRDFParser($a = '')
    {
        return self::getParser('RDF', $a);
    }

    public static function getRDFXMLParser($a = '')
    {
        return self::getParser('RDFXML', $a);
    }

    public static function getTurtleParser($a = '')
    {
        return self::getParser('Turtle', $a);
    }

    public static function getRSSParser($a = '')
    {
        return self::getParser('RSS', $a);
    }

    public static function getSemHTMLParser($a = '')
    {
        return self::getParser('SemHTML', $a);
    }

    public static function getSPARQLParser($a = '')
    {
        return self::getComponent('SPARQLParser', $a);
    }

    public static function getSPARQLPlusParser($a = '')
    {
        return self::getParser('SPARQLPlus', $a);
    }

    public static function getSPARQLXMLResultParser($a = '')
    {
        return self::getParser('SPARQLXMLResult', $a);
    }

    public static function getJSONParser($a = '')
    {
        return self::getParser('JSON', $a);
    }

    public static function getSGAJSONParser($a = '')
    {
        return self::getParser('SGAJSON', $a);
    }

    public static function getCBJSONParser($a = '')
    {
        return self::getParser('CBJSON', $a);
    }

    public static function getSPARQLScriptParser($a = '')
    {
        return self::getParser('SPARQLScript', $a);
    }

    /* store */

    public static function getStore($a = '', $caller = '')
    {
        return self::getComponent('Store', [], $caller);
    }

    public static function getStoreEndpoint($a = '', $caller = '')
    {
        return self::getComponent('StoreEndpoint', $a, $caller);
    }

    public static function getRemoteStore($a = '', $caller = '')
    {
        return self::getComponent('RemoteStore', $a, $caller);
    }

    public static function getMemStore($a = '')
    {
        return self::getComponent('MemStore', $a);
    }

    /* serializers */

    public static function getSer($prefix, $a = '')
    {
        return self::getComponent($prefix.'Serializer', $a);
    }

    public static function getTurtleSerializer($a = '')
    {
        return self::getSer('Turtle', $a);
    }

    public static function getRDFXMLSerializer($a = '')
    {
        return self::getSer('RDFXML', $a);
    }

    public static function getNTriplesSerializer($a = '')
    {
        return self::getSer('NTriples', $a);
    }

    public static function getRDFJSONSerializer($a = '')
    {
        return self::getSer('RDFJSON', $a);
    }

    public static function getPOSHRDFSerializer($a = '')
    {/* deprecated */
        return self::getSer('POSHRDF', $a);
    }

    public static function getMicroRDFSerializer($a = '')
    {
        return self::getSer('MicroRDF', $a);
    }

    public static function getRSS10Serializer($a = '')
    {
        return self::getSer('RSS10', $a);
    }

    public static function getJSONLDSerializer($a = '')
    {
        return self::getSer('JSONLD', $a);
    }

    /* sparqlscript */

    public static function getSPARQLScriptProcessor($a = '')
    {
        return self::getComponent('SPARQLScriptProcessor', $a);
    }
}
