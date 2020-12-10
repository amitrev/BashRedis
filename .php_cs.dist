<?php

return PhpCsFixer\Config::create()
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@DoctrineAnnotation' => true,
        'strict_param' => true,
        'yoda_style' => false,
        'concat_space' => ['spacing' => 'one'],
        'compact_nullable_typehint' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'multiline_comment_opening_closing' => true,
        'array_indentation' => true,
        'explicit_string_variable' => true,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__ . '/src')
            ->append([__FILE__])
    )
;
