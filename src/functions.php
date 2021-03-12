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

/**
 * @return array<string,string>
 */
function splitURI($v): array
{
    /*
     * the following namespaces may lead to conflated URIs,
     * we have to set the split position manually
     */
    if (strpos($v, 'www.w3.org')) {
        $specials = [
            'http://www.w3.org/XML/1998/namespace',
            'http://www.w3.org/2005/Atom',

    /**
     * @todo port to NamespaceHelper
     */'http://www.w3.org/1999/xhtml',
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
