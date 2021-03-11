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

class ARC2_StoreQueryHandler extends ARC2_Class
{
    protected array $errors = [];
    protected ARC2_Store $store;
    protected string $xsd = NamespaceHelper::NAMESPACE_XSD;

    public function __construct($a, &$caller)
    {
        parent::__construct($a, $caller);

        $this->allow_extension_functions = $this->v('store_allow_extension_functions', 1, $this->a);
        $this->handler_type = '';
    }

    public function getTermID($val, $term = '')
    {
        return $this->store->getTermID($val, $term);
    }

    public function hasHashColumn($tbl)
    {
        return $this->store->hasHashColumn($tbl);
    }

    public function getValueHash($val)
    {
        return $this->store->getValueHash($val);
    }
}
