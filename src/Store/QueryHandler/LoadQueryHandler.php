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

namespace sweetrdf\InMemoryStoreSqlite\Store\QueryHandler;

use function sweetrdf\InMemoryStoreSqlite\calcURI;
use function sweetrdf\InMemoryStoreSqlite\getNormalizedValue;
use sweetrdf\InMemoryStoreSqlite\Store\TurtleLoader;

class LoadQueryHandler extends QueryHandler
{
    private string $target_graph;

    /**
     * @todo required?
     */
    private int $t_count;

    private int $write_buffer_size = 2500;

    public function runQuery($infos, $data = '', $keep_bnode_ids = 0)
    {
        $url = $infos['query']['url'];
        $graph = $infos['query']['target_graph'];
        $this->target_graph = $graph ? calcURI($graph) : calcURI($url);
        $this->keep_bnode_ids = $keep_bnode_ids;

        // remove parameters
        $parserLogger = $this->store->getLoggerPool()->createNewLogger('Turtle');
        $loader = new TurtleLoader($parserLogger, $this->store->getNamespaceHelper());
        $loader->setCaller($this);

        /* logging */
        $this->t_count = 0;
        $this->t_start = 0;
        /* load and parse */
        $this->max_term_id = $this->getMaxTermID();
        $this->max_triple_id = $this->getMaxTripleID();

        $this->term_ids = [];
        $this->triple_ids = [];
        $this->sql_buffers = [];
        $loader->parse($url, $data);

        /* done */
        $this->checkSQLBuffers(1);

        return [
            't_count' => $this->t_count,
            'load_time' => 0,
        ];
    }

    public function addT($s, $p, $o, $s_type, $o_type, $o_dt = '', $o_lang = '')
    {
        $type_ids = ['uri' => '0', 'bnode' => '1', 'literal' => '2'];
        $g = $this->getStoredTermID($this->target_graph, '0', 'id');
        $s = (('bnode' == $s_type) && !$this->keep_bnode_ids) ? '_:b'.abs(crc32($g.$s)).'_'.(\strlen($s) > 12 ? substr(substr($s, 2), -10) : substr($s, 2)) : $s;
        $o = (('bnode' == $o_type) && !$this->keep_bnode_ids) ? '_:b'.abs(crc32($g.$o)).'_'.(\strlen($o) > 12 ? substr(substr($o, 2), -10) : substr($o, 2)) : $o;
        /* triple */
        $t = [
            's' => $this->getStoredTermID($s, $type_ids[$s_type], 's'),
            'p' => $this->getStoredTermID($p, '0', 'id'),
            'o' => $this->getStoredTermID($o, $type_ids[$o_type], 'o'),
            'o_lang_dt' => $this->getStoredTermID($o_dt.$o_lang, $o_dt ? '0' : '2', 'id'),
            'o_comp' => getNormalizedValue($o),
            's_type' => $type_ids[$s_type],
            'o_type' => $type_ids[$o_type],
        ];
        $t['t'] = $this->getTripleID($t);
        if (\is_array($t['t'])) {/* t exists already */
            $t['t'] = $t['t'][0];
        } else {
            $this->bufferTripleSQL($t);
        }
        /* g2t */
        $g2t = ['g' => $g, 't' => $t['t']];
        $this->bufferGraphSQL($g2t);
        ++$this->t_count;
        /* check buffers */
        if (0 == ($this->t_count % $this->write_buffer_size)) {
            $force_write = 1;
            $reset_buffers = (0 == ($this->t_count % ($this->write_buffer_size * 2)));
            $refresh_lock = (0 == ($this->t_count % 25000));
            $split_tables = (0 == ($this->t_count % ($this->write_buffer_size * 10)));
            $this->checkSQLBuffers($force_write, $reset_buffers, $refresh_lock, $split_tables);
        }
    }

    public function getMaxTermID(): int
    {
        $sql = '';
        foreach (['id2val', 's2val', 'o2val'] as $tbl) {
            $sql .= $sql ? ' UNION ' : '';
            $sql .= 'SELECT MAX(id) as id FROM '.$tbl;
        }
        $r = 0;

        $rows = $this->store->getDBObject()->fetchList($sql);

        if (\is_array($rows)) {
            foreach ($rows as $row) {
                $r = ($r < $row['id']) ? $row['id'] : $r;
            }
        }

        return $r + 1;
    }

    /**
     * @todo change DB schema and avoid using this function because it does not protect against race conditions
     *
     * @return int
     */
    public function getMaxTripleID()
    {
        $sql = 'SELECT MAX(t) AS `id` FROM triple';

        $row = $this->store->getDBObject()->fetchRow($sql);
        if (isset($row['id'])) {
            return $row['id'] + 1;
        }

        return 1;
    }

    public function getStoredTermID($val, $type_id, $tbl)
    {
        /* buffered */
        if (isset($this->term_ids[$val])) {
            if (!isset($this->term_ids[$val][$tbl])) {
                foreach (['id', 's', 'o'] as $other_tbl) {
                    if (isset($this->term_ids[$val][$other_tbl])) {
                        $this->term_ids[$val][$tbl] = $this->term_ids[$val][$other_tbl];
                        $this->bufferIDSQL($tbl, $this->term_ids[$val][$tbl], $val, $type_id);
                        break;
                    }
                }
            }

            return $this->term_ids[$val][$tbl];
        }
        /* db */
        $sub_tbls = ('id' == $tbl)
            ? ['id2val', 's2val', 'o2val']
            : ('s' == $tbl
                ? ['s2val', 'id2val', 'o2val']
                : ['o2val', 'id2val', 's2val']
            );

        foreach ($sub_tbls as $sub_tbl) {
            $id = 0;
            /* via hash */
            if (preg_match('/^(s2val|o2val)$/', $sub_tbl)) {
                $sql = 'SELECT id, val
                    FROM '.$sub_tbl.'
                    WHERE val_hash = "'.$this->getValueHash($val).'"';

                $rows = $this->store->getDBObject()->fetchList($sql);
                if (\is_array($rows)) {
                    foreach ($rows as $row) {
                        if ($row['val'] == $val) {
                            $id = $row['id'];
                            break;
                        }
                    }
                }
            } else {
                $binaryValue = $this->store->getDBObject()->escape($val);
                if (false !== empty($binaryValue)) {
                    $sql = 'SELECT id FROM '.$sub_tbl." WHERE val = '".$binaryValue."'";

                    $row = $this->store->getDBObject()->fetchRow($sql);
                    if (\is_array($row) && isset($row['id'])) {
                        $id = $row['id'];
                    }
                }
            }
            if (0 < $id) {
                $this->term_ids[$val] = [$tbl => $id];
                if ($sub_tbl != $tbl.'2val') {
                    $this->bufferIDSQL($tbl, $id, $val, $type_id);
                }
                break;
            }
        }
        /* new */
        if (!isset($this->term_ids[$val])) {
            $this->term_ids[$val] = [$tbl => $this->max_term_id];
            $this->bufferIDSQL($tbl, $this->max_term_id, $val, $type_id);
            ++$this->max_term_id;
        }

        return $this->term_ids[$val][$tbl];
    }

    public function getTripleID($t)
    {
        $val = serialize($t);
        /* buffered */
        if (isset($this->triple_ids[$val])) {
            /* hack for "don't insert this triple" */
            return [$this->triple_ids[$val]];
        }
        /* db */
        $sql = 'SELECT t
                  FROM triple
                 WHERE s = '.$t['s'].'
                    AND p = '.$t['p'].'
                    AND o = '.$t['o'].'
                    AND o_lang_dt = '.$t['o_lang_dt'].'
                    AND s_type = '.$t['s_type'].'
                    AND o_type = '.$t['o_type'].'
                 LIMIT 1';
        $row = $this->store->getDBObject()->fetchRow($sql);
        if (isset($row['t'])) {
            /* hack for "don't insert this triple" */
            $this->triple_ids[$val] = $row['t'];

            return [$row['t']];
        } else {
            /* new */
            $this->triple_ids[$val] = $this->max_triple_id;
            ++$this->max_triple_id;

            return $this->triple_ids[$val];
        }
    }

    public function bufferTripleSQL($t)
    {
        $tbl = 'triple';
        $sql = ', ';

        $sqlHead = 'INSERT OR IGNORE INTO ';

        if (!isset($this->sql_buffers[$tbl])) {
            $this->sql_buffers[$tbl] = $sqlHead;
            $this->sql_buffers[$tbl] .= $tbl;
            $this->sql_buffers[$tbl] .= ' (t, s, p, o, o_lang_dt, o_comp, s_type, o_type) VALUES';
            $sql = ' ';
        }

        $oCompEscaped = $this->store->getDBObject()->escape($t['o_comp']);

        $this->sql_buffers[$tbl] .= $sql.'('.$t['t'].', '.$t['s'].', '.$t['p'].', ';
        $this->sql_buffers[$tbl] .= $t['o'].', '.$t['o_lang_dt'].", '";
        $this->sql_buffers[$tbl] .= $oCompEscaped."', ".$t['s_type'].', '.$t['o_type'].')';
    }

    public function bufferGraphSQL($g2t)
    {
        $tbl = 'g2t';
        $sql = ', ';

        /*
         * Use appropriate INSERT syntax, depending on the DBS.
         */
        $sqlHead = 'INSERT OR IGNORE INTO ';

        if (!isset($this->sql_buffers[$tbl])) {
            $this->sql_buffers[$tbl] = $sqlHead.$tbl.' (g, t) VALUES';
            $sql = ' ';
        }
        $this->sql_buffers[$tbl] .= $sql.'('.$g2t['g'].', '.$g2t['t'].')';
    }

    public function bufferIDSQL($tbl, $id, $val, $val_type)
    {
        $tbl = $tbl.'2val';
        if ('id2val' == $tbl) {
            $cols = 'id, val, val_type';
            $vals = '('.$id.", '".$this->store->getDBObject()->escape($val)."', ".$val_type.')';
        } elseif (preg_match('/^(s2val|o2val)$/', $tbl)) {
            $cols = 'id, val_hash, val';
            $vals = '('.$id.", '"
                .$this->getValueHash($val)
                ."', '"
                .$this->store->getDBObject()->escape($val)
                ."')";
        } else {
            $cols = 'id, val';
            $vals = '('.$id.", '".$this->store->getDBObject()->escape($val)."')";
        }
        if (!isset($this->sql_buffers[$tbl])) {
            $this->sql_buffers[$tbl] = '';
            $sqlHead = 'INSERT OR IGNORE INTO ';

            $sql = $sqlHead.$tbl.'('.$cols.') VALUES ';
        } else {
            $sql = ', ';
        }
        $sql .= $vals;
        $this->sql_buffers[$tbl] .= $sql;
    }

    public function checkSQLBuffers($force_write = 0, $reset_id_buffers = 0)
    {
        foreach (['triple', 'g2t', 'id2val', 's2val', 'o2val'] as $tbl) {
            $buffer_size = isset($this->sql_buffers[$tbl]) ? 1 : 0;
            if ($buffer_size && $force_write) {
                $this->store->getDBObject()->exec($this->sql_buffers[$tbl]);
                /* table error */
                $this->store->getDBObject()->getErrorMessage();
                unset($this->sql_buffers[$tbl]);

                /* reset term id buffers */
                if ($reset_id_buffers) {
                    $this->term_ids = [];
                    $this->triple_ids = [];
                }
            }
        }

        return 1;
    }
}
