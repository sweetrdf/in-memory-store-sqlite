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
    private string $base;

    private array $errors = [];

    private array $stream = [];

    public function __destruct()
    {
        $this->closeStream();
    }

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

    public function getBase(): string
    {
        return $this->base;
    }

    public function activate($path, $data = '')
    {
        $this->base = calcBase($path);
        $this->uri = calcURI($path, $this->base);

        $this->stream = !empty($data)
            ? $this->getDataStream($data)
            : $this->getSocketStream($this->base);
    }

    public function getDataStream($data): array
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

    public function getSocketStream($url): array
    {
        if ('file://' == $url) {
            throw new Exception('Error: file does not exists or is not accessible');
        }
        $scheme = strtolower(parse_url($url)['scheme']);
        if ('file' == $scheme) {
            return $this->getFileSocket($url);
        } else {
            return $this->getDataStream('');
        }
    }

    public function getFileSocket($url)
    {
        $parts = parse_url($url);
        $s = file_exists($parts['path']) ? fopen($parts['path'], 'r') : false;
        if (!$s) {
            throw new Exception('Socket error: Could not open "'.$parts['path'].'"');
        }

        return [
            'type' => 'socket',
            'socket' => &$s,
            'headers' => [],
            'pos' => 0,
            'size' => filesize($parts['path']),
            'buffer' => ''
        ];
    }

    public function readStream(int $d_size = 1024): string
    {
        $s = $this->stream;
        $r = $this->stream['buffer'];

        $s['buffer'] = '';
        if ($s['size']) {
            $d_size = min($d_size, $this->stream['size'] - $this->stream['pos']);
        }

        if ('data' == $this->stream['type']) {
            if ($d_size > 0) {
                $d = substr($this->stream['data'], $s['pos'], $d_size);
            } else {
                $d = '';
            }
        } elseif ('socket' == $this->stream['type']) {
            if (0 < $d_size && !feof($this->stream['socket'])) {
                $d = fread($s['socket'], $d_size);
            } else {
                $d = '';
            }
        }

        $s['pos'] += strlen($d);

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
