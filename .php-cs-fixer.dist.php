<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'vendors'])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true
    ])
    ->setFinder($finder)
    ;
