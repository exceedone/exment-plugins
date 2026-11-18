<?php
declare(strict_types=1);

// Same rules as exceedone/exment. Checks every plugin folder in this repository.
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['.github', 'vendor'])
    ->name('*.php')
    ->notName('*.blade.php');
$config = new PhpCsFixer\Config();

return $config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PhpCsFixer:risky' => false,
        'blank_line_after_opening_tag' => false,
        'linebreak_after_opening_tag' => false,
        'declare_strict_types' => false,
        'phpdoc_types_order' => [
            'null_adjustment' => 'none',
            'sort_algorithm' => 'none',
        ],
        'no_superfluous_phpdoc_tags' => false,
        'not_operator_with_successor_space' => false,
        'blank_line_after_namespace' => true,
    ])
    ->setFinder($finder);
