<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ .DIRECTORY_SEPARATOR.'src')
    ->in(__DIR__ .DIRECTORY_SEPARATOR.'tests')
    ->files([
        __DIR__.DIRECTORY_SEPARATOR.'.php-cs-fixer.dist.php',
    ])
    ->name('*.php')
;

$config = new Config();
$config
    ->setFinder($finder)
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'phpdoc_summary' => false,
        'no_unused_imports' => true,
    ])
;

return $config;
