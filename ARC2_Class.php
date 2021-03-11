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

use sweetrdf\InMemoryStoreSqlite\NamespaceHelper;
use sweetrdf\InMemoryStoreSqlite\PDOSQLiteAdapter;
use sweetrdf\InMemoryStoreSqlite\Serializer\TurtleSerializer;

/**
 * ARC2 base class.
 *
 * @author Benjamin Nowack
 * @license W3C Software License and GPL
 * @homepage <https://github.com/semsol/arc2>
 */
class ARC2_Class
{
    protected $db_object;

    public function __construct($a, &$caller)
    {
        $this->a = is_array($a) ? $a : [];
        $this->caller = $caller;
        $this->__init();
    }

    public function __init()
    {
        $this->ns_count = 0;
        $rdf = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
        $this->nsp = [$rdf => 'rdf'];
        $this->used_ns = [$rdf];
        $this->ns = array_merge(['rdf' => $rdf], $this->v('ns', [], $this->a));

        $this->base = NamespaceHelper::BASE_NAMESPACE;
        $this->errors = [];
        $this->warnings = [];
    }

    public function v($name, $default = false, $o = false)
    {/* value if set */
        if (false === $o) {
            $o = $this;
        }
        if (is_array($o)) {
            return isset($o[$name]) ? $o[$name] : $default;
        }

        return isset($o->$name) ? $o->$name : $default;
    }

    public function v1($name, $default = false, $o = false)
    {/* value if 1 (= not empty) */
        if (false === $o) {
            $o = $this;
        }
        if (is_array($o)) {
            return (isset($o[$name]) && $o[$name]) ? $o[$name] : $default;
        }

        return (isset($o->$name) && $o->$name) ? $o->$name : $default;
    }

    public function m($name, $a = false, $default = false, $o = false)
    {
        /* call method */
        if (false === $o) {
            $o = $this;
        }

        return method_exists($o, $name) ? $o->$name($a) : $default;
    }

    /**
     * @todo handle 51+ exceptions being thrown during execution?!
     */
    public function addError($v)
    {
        if (!in_array($v, $this->errors)) {
            $this->errors[] = $v;
        }
        if ($this->caller && method_exists($this->caller, 'addError')) {
            $glue = strpos($v, ' in ') ? ' via ' : ' in ';
            $this->caller->addError($v.$glue.static::class);
        }

        return false;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getWarnings()
    {
        return $this->warnings;
    }

    public function resetErrors()
    {
        $this->errors = [];
    }

    public function splitURI($v)
    {
        return ARC2::splitURI($v);
    }

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
        $parts = $this->splitURI($v);
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

    public function setPrefix($prefix, $ns)
    {
        $this->ns[$prefix] = $ns;
        $this->nsp[$ns] = $prefix;

        return $this;
    }

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

    public function calcURI($path, $base = '')
    {
        /* quick check */
        if (preg_match("/^[a-z0-9\_]+\:/i", $path)) {/* abs path or bnode */
            return $path;
        }
        if (preg_match('/^\$\{.*\}/', $path)) {/* placeholder, assume abs URI */
            return $path;
        }
        if (preg_match("/^\/\//", $path)) {/* net path, assume http */
            return 'http:'.$path;
        }
        /* other URIs */
        $base = $base ?: $this->base;
        $base = preg_replace('/\#.*$/', '', $base);
        if (true === $path) {/* empty (but valid) URIref via turtle parser: <> */
            return $base;
        }
        $path = preg_replace("/^\.\//", '', $path);
        $root = preg_match('/(^[a-z0-9]+\:[\/]{1,3}[^\/]+)[\/|$]/i', $base, $m) ? $m[1] : $base; /* w/o trailing slash */
        $base .= ($base == $root) ? '/' : '';
        if (preg_match('/^\//', $path)) {/* leading slash */
            return $root.$path;
        }
        if (!$path) {
            return $base;
        }
        if (preg_match('/^([\#\?])/', $path, $m)) {
            return preg_replace('/\\'.$m[1].'.*$/', '', $base).$path;
        }
        if (preg_match('/^(\&)(.*)$/', $path, $m)) {/* not perfect yet */
            return preg_match('/\?/', $base) ? $base.$m[1].$m[2] : $base.'?'.$m[2];
        }
        if (preg_match("/^[a-z0-9]+\:/i", $path)) {/* abs path */
            return $path;
        }
        /* rel path: remove stuff after last slash */
        $base = substr($base, 0, strrpos($base, '/') + 1);

        /* resolve ../ */
        while (preg_match('/^(\.\.\/)(.*)$/', $path, $m)) {
            $path = $m[2];
            $base = ($base == $root.'/') ? $base : preg_replace('/^(.*\/)[^\/]+\/$/', '\\1', $base);
        }

        return $base.$path;
    }

    public function calcBase(string $path): string
    {
        $r = $path;
        $r = preg_replace('/\#.*$/', '', $r); /* remove hash */
        $r = preg_replace('/^\/\//', 'http://', $r); /* net path (//), assume http */
        if (preg_match('/^[a-z0-9]+\:/', $r)) {/* scheme, abs path */
            while (preg_match('/^(.+\/)(\.\.\/.*)$/U', $r, $m)) {
                $r = $this->calcURI($m[1], $m[2]);
            }

            return $r;
        }

        return 'file://'.realpath($r); /* real path */
    }

    public function toTurtle($v)
    {
        $ser = new TurtleSerializer([], $this);

        return (isset($v[0]) && isset($v[0]['s']))
            ? $ser->getSerializedTriples($v)
            : $ser->getSerializedIndex($v);
    }

    /* central DB query hook */

    public function getDBObjectFromARC2Class()
    {
        if (null == $this->db_object) {
            if (false == isset($this->a['db_adapter'])) {
                $this->a['db_adapter'] = 'mysqli';
            }
            $this->db_object = new PDOSQLiteAdapter();
        }

        return $this->db_object;
    }

    /**
     * Dont use this function to directly query the database. It currently works only with mysqli DB adapter.
     *
     * @param string $sql        SQL query
     * @param mysqli $con        Connection
     * @param int    $log_errors 1 if you want to log errors. Default is 0
     *
     * @return mysqli Result
     *
     * @deprecated since 2.4.0
     */
    public function queryDB($sql, $con, $log_errors = 0)
    {
        // create connection using an adapter, if not available yet
        $this->getDBObjectFromARC2Class($con);

        $r = $this->db_object->mysqliQuery($sql);

        if ($log_errors && !empty($this->db_object->getErrorMessage())) {
            $this->addError($this->db_object->getErrorMessage());
        }

        return $r;
    }
}
