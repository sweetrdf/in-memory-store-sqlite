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

return [
    'db_adapter' => 'pdo',
    'db_pdo_protocol' => 'sqlite',
    'store_name' => 'arc',
]; // */

return [
    'db_name' => 'arc2_test',
    'db_user' => 'root',
    'db_pwd' => 'Pass123',
    'db_host' => 'db',
    'db_port' => 3306,
    'db_adapter' => 'mysqli',
    //'db_pdo_protocol' => 'mysql',
    'store_name' => 'arc',
];
