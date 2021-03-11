<?php

namespace sweetrdf\InMemoryStoreSqlite;

function calcURI(string $path, ?string $base = null): string
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
    $base = $base ?: NamespaceHelper::BASE_NAMESPACE;
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

function calcBase(string $path): string
{
    $r = $path;
    $r = preg_replace('/\#.*$/', '', $r); /* remove hash */
    $r = preg_replace('/^\/\//', 'http://', $r); /* net path (//), assume http */
    if (preg_match('/^[a-z0-9]+\:/', $r)) {/* scheme, abs path */
        while (preg_match('/^(.+\/)(\.\.\/.*)$/U', $r, $m)) {
            $r = calcURI($m[1], $m[2]);
        }

        return $r;
    }

    return 'file://'.realpath($r); /* real path */
}
