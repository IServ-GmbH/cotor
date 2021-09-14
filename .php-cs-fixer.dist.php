<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],

        // Stricter rules than PSR-12
        'concat_space' => ['spacing' => 'one'],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'no_empty_statement' => true,

        // Those rules are in discussion whether the PSR include these
        'function_typehint_space' => true,
        'no_leading_namespace_whitespace' => true,
    ])
    ->setFinder($finder)
;

