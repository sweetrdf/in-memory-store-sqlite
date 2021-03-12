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
    private ?string $base;

    private array $stream = [];

    public function getBase(): ?string
    {
        return $this->base;
    }

    public function activate($path, $data)
    {
        $this->base = calcBase($path);
        $this->uri = calcURI($path, $this->base);

        $this->stream = [
            'type' => 'data',
            'pos' => 0,
            'headers' => [],
            'size' => strlen($data),
            'data' => $data,
            'buffer' => '',
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
        }

        $s['pos'] += strlen($d);

        $this->stream = $s;

        return $r.$d;
    }
}
