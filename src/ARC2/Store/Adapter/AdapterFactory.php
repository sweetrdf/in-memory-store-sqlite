<?php

/**
 * @author Benjamin Nowack <bnowack@semsol.com>
 * @author Konrad Abicht <konrad.abicht@pier-and-peer.com>
 * @license W3C Software License and GPL
 * @homepage <https://github.com/semsol/arc2>
 */

namespace ARC2\Store\Adapter;

use Exception;

/**
 * It provides an adapter instance for requested adapter name.
 */
class AdapterFactory
{
    /**
     * @param string $adapterName
     * @param array  $configuration Default is array()
     *
     * @throws Exception if unknown adapter name was given
     */
    public function getInstanceFor($adapterName, $configuration = [])
    {
        return new PDOSQLiteAdapter($configuration);
    }

    /**
     * @return array
     */
    public function getSupportedAdapters()
    {
        return ['mysqli', 'pdo'];
    }
}
