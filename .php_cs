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

$header = <<<'EOF'
 This file is part of the sweetrdf/InMemoryStoreSqlite package and licensed under
 the terms of the GPL-3 license.

 (c) Konrad Abicht <hi@inspirito.de>

 For the full copyright and license information, please view the LICENSE
 file that was distributed with this source code.
EOF;

return PhpCsFixer\Config::create()
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'header_comment' => ['header' => $header],
        'array_indentation' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        PhpCsFixer\Finder::create()
        ->in(__DIR__.'/src')
        ->in(__DIR__.'/parsers')
        ->in(__DIR__.'/serializers')
        ->in(__DIR__.'/sparqlscript')
        ->in(__DIR__.'/tests')
        ->name('*.php')
        ->append([
            __FILE__,
            'ARC2_Class.php',
            'ARC2_Graph.php',
            'ARC2_Reader.php',
            'ARC2_Resource.php',
        ])
    );
