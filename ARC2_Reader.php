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

use function sweetrdf\InMemoryStoreSqlite\calcBase;
use function sweetrdf\InMemoryStoreSqlite\calcURI;

class ARC2_Reader
{
    private array $errors = [];

    /**
     * @todo refactor that
     */
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

    /**
     * @todo refactor that
     */
    public function m($name, $a = false, $default = false, $o = false)
    {
        /* call method */
        if (false === $o) {
            $o = $this;
        }

        return method_exists($o, $name) ? $o->$name($a) : $default;
    }

    /**
     * @todo replace by Logger
     */
    private function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function activate($path, $data = '', $ping_only = 0)
    {
        /* data uri? */
        if (!$data && preg_match('/^data\:([^\,]+)\,(.*)$/', $path, $m)) {
            $path = '';
            $data = preg_match('/base64/', $m[1]) ? base64_decode($m[2]) : rawurldecode($m[2]);
        }
        $this->base = calcBase($path);
        $this->uri = calcURI($path, $this->base);
        $this->stream = $data
            ? $this->getDataStream($data)
            : $this->getSocketStream($this->base, $ping_only);
    }

    public function getDataStream($data)
    {
        return [
            'type' => 'data',
            'pos' => 0,
            'headers' => [],
            'size' => strlen($data),
            'data' => $data,
            'buffer' => '',
        ];
    }

    public function getSocketStream($url)
    {
        if ('file://' == $url) {
            return $this->addError('Error: file does not exists or is not accessible');
        }
        $parts = parse_url($url);
        $mappings = ['file' => 'File', 'http' => 'HTTP', 'https' => 'HTTP'];
        if ($scheme = $this->v(strtolower($parts['scheme']), '', $mappings)) {
            return $this->m('get'.$scheme.'Socket', $url, $this->getDataStream(''));
        }
    }

    public function getFileSocket($url)
    {
        $parts = parse_url($url);
        $s = file_exists($parts['path']) ? fopen($parts['path'], 'r') : false;
        if (!$s) {
            return $this->addError('Socket error: Could not open "'.$parts['path'].'"');
        }

        return ['type' => 'socket', 'socket' => &$s, 'headers' => [], 'pos' => 0, 'size' => filesize($parts['path']), 'buffer' => ''];
    }

    /**
     * @todo port it to a simple line reader
     */
    public function readStream($buffer_xml = true, $d_size = 1024)
    {
        //if (!$s = $this->v('stream')) return '';
        if (!$s = $this->v('stream')) {
            return $this->addError('missing stream in "readStream" '.$this->uri);
        }
        $s_type = $this->v('type', '', $s);
        $r = $s['buffer'];
        $s['buffer'] = '';
        if ($s['size']) {
            $d_size = min($d_size, $s['size'] - $s['pos']);
        }
        /* data */
        if ('data' == $s_type) {
            $d = ($d_size > 0) ? substr($s['data'], $s['pos'], $d_size) : '';
        }
        /* socket */
        elseif ('socket' == $s_type) {
            $d = ($d_size > 0) && !feof($s['socket']) ? fread($s['socket'], $d_size) : '';
        }
        $eof = $d ? false : true;
        /* chunked despite HTTP 1.0 request */
        if (isset($s['headers']) && isset($s['headers']['transfer-encoding']) && ('chunked' == $s['headers']['transfer-encoding'])) {
            $d = preg_replace('/(^|[\r\n]+)[0-9a-f]{1,4}[\r\n]+/', '', $d);
        }
        $s['pos'] += strlen($d);
        if ($buffer_xml) {/* stop after last closing xml tag (if available) */
            if (preg_match('/^(.*\>)([^\>]*)$/s', $d, $m)) {
                $d = $m[1];
                $s['buffer'] = $m[2];
            } elseif (!$eof) {
                $s['buffer'] = $r.$d;
                $this->stream = $s;

                return $this->readStream(true, $d_size);
            }
        }
        $this->stream = $s;

        return $r.$d;
    }

    public function closeStream()
    {
        if (isset($this->stream)) {
            if ('socket' == $this->v('type', 0, $this->stream) && !empty($this->stream['socket'])) {
                fclose($this->stream['socket']);
            }
            unset($this->stream);
        }
    }
}
