<?php

/*
 *  This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 *  the terms of the GPL-3 license.
 *
 *  (c) Konrad Abicht <hi@inspirito.de>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

class ARC2_Reader extends ARC2_Class
{
    public function __construct($a, &$caller)
    {
        parent::__construct($a, $caller);
    }

    public function __init()
    {
        /*
            inc_path, proxy_host, proxy_port, proxy_skip, http_accept_header, http_user_agent_header,
            max_redirects
         */
        parent::__init();
        $this->http_method = $this->v('http_method', 'GET', $this->a);
        $this->message_body = $this->v('message_body', '', $this->a);
        $this->http_accept_header = $this->v('http_accept_header', 'Accept: application/rdf+xml; q=0.9, text/turtle; q=0.8, */*; q=0.1', $this->a);
        $this->http_user_agent_header = $this->v('http_user_agent_header', 'User-Agent: ARC Reader (https://github.com/semsol/arc2)', $this->a);
        $this->http_custom_headers = $this->v('http_custom_headers', '', $this->a);
        $this->max_redirects = $this->v('max_redirects', 3, $this->a);
        $this->format = $this->v('format', false, $this->a);
        $this->redirects = [];
        $this->stream_id = '';
        $this->timeout = $this->v('reader_timeout', 30, $this->a);
        $this->response_headers = [];
        $this->digest_auth = 0;
        $this->auth_infos = $this->v('reader_auth_infos', [], $this->a);
    }

    public function activate($path, $data = '', $ping_only = 0, $timeout = 0)
    {
        $this->ping_only = $ping_only;
        if ($timeout) {
            $this->timeout = $timeout;
        }
        $id = md5($path.' '.$data);
        if ($this->stream_id != $id) {
            $this->stream_id = $id;
            /* data uri? */
            if (!$data && preg_match('/^data\:([^\,]+)\,(.*)$/', $path, $m)) {
                $path = '';
                $data = preg_match('/base64/', $m[1]) ? base64_decode($m[2]) : rawurldecode($m[2]);
            }
            $this->base = $this->calcBase($path);
            $this->uri = $this->calcURI($path, $this->base);
            $this->stream = $data
                ? $this->getDataStream($data)
                : $this->getSocketStream($this->base, $ping_only);
        }
    }

    public function setDigestAuthCredentials($creds, $url)
    {
        $path = $this->v1('path', '/', parse_url($url));
        $auth = '';
        $hs = $this->getResponseHeaders();
        /* initial 401 */
        $h = $this->v('www-authenticate', '', $hs);
        if ($h && preg_match('/Digest/i', $h)) {
            $auth = 'Digest ';
            /* Digest realm="$realm", nonce="$nonce", qop="auth", opaque="$opaque" */
            $ks = ['realm', 'nonce', 'opaque']; /* skipping qop, assuming "auth" */
            foreach ($ks as $i => $k) {
                $$k = preg_match('/'.$k.'=\"?([^\"]+)\"?/i', $h, $m) ? $m[1] : '';
                $auth .= ($i ? ', ' : '').$k.'="'.$$k.'"';
                $this->auth_infos[$k] = $$k;
            }
            $this->auth_infos['auth'] = $auth;
            $this->auth_infos['request_count'] = 1;
        }
        /* initial 401 or repeated request */
        if ($this->v('auth', 0, $this->auth_infos)) {
            $qop = 'auth';
            $auth = $this->auth_infos['auth'];
            $rc = $this->auth_infos['request_count'];
            $realm = $this->auth_infos['realm'];
            $nonce = $this->auth_infos['nonce'];
            $ha1 = md5($creds['user'].':'.$realm.':'.$creds['pass']);
            $ha2 = md5($this->http_method.':'.$path);
            $nc = dechex($rc);
            $cnonce = dechex($rc * 2);
            $resp = md5($ha1.':'.$nonce.':'.$nc.':'.$cnonce.':'.$qop.':'.$ha2);
            $auth .= ', username="'.$creds['user'].'"'.
        ', uri="'.$path.'"'.
        ', qop='.$qop.''.
        ', nc='.$nc.
        ', cnonce="'.$cnonce.'"'.
        ', uri="'.$path.'"'.
        ', response="'.$resp.'"'.
      '';
            $this->auth_infos['request_count'] = $rc + 1;
        }
        if (!$auth) {
            return 0;
        }
        $h = in_array('proxy', $creds) ? 'Proxy-Authorization' : 'Authorization';
        $this->addCustomHeaders($h.': '.$auth);
    }

    public function useProxy($url)
    {
        if (!$this->v1('proxy_host', 0, $this->a)) {
            return false;
        }
        $skips = $this->v1('proxy_skip', [], $this->a);
        foreach ($skips as $skip) {
            if (false !== strpos($url, $skip)) {
                return false;
            }
        }

        return true;
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
        $s = file_exists($parts['path']) ? @fopen($parts['path'], 'r') : false;
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
