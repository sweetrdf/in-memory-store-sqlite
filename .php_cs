<?php

return PhpCsFixer\Config::create()
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_indentation' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        PhpCsFixer\Finder::create()
        ->in(__DIR__.'/src')
        ->in(__DIR__.'/parsers')
        ->in(__DIR__.'/sparqlscript')
        ->in(__DIR__.'/tests')
        ->name('*.php')
        ->append([
            __FILE__,
            'ARC2.php',
            'ARC2_Class.php',
            'ARC2_Reader.php',
        ])
    );
