<?php

/*
 *  This file is part of the quickrdf/InMemoryStoreSqlite package and licensed under
 *  the terms of the GPL-3 license.
 *
 *  (c) Konrad Abicht <hi@inspirito.de>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

ARC2::inc('RDFXMLSerializer');

class ARC2_RSS10Serializer extends ARC2_RDFXMLSerializer
{
    public function __construct($a, &$caller)
    {
        parent::__construct($a, $caller);
    }

    public function __init()
    {
        parent::__init();
        $this->content_header = 'application/rss+xml';
        $this->default_ns = 'http://purl.org/rss/1.0/';
        $this->type_nodes = true;
    }
}
