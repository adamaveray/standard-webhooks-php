<?php
declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

require_once __DIR__ . '/vendor/autoload.php';

$finder = (new Finder())->exclude(['node_modules', 'vendor'])->in(__DIR__);

return (new Config())
  ->setRules([
    '@PER-CS' => true,
    '@PHP8x4Migration' => true,

    // Defer to Prettier
    'braces_position' => false,
    'class_definition' => false,
    'method_argument_space' => false,
    'new_expression_parentheses' => false,
    'no_extra_blank_lines' => false,
    'operator_linebreak' => false,
    'single_line_empty_body' => false,
  ])
  ->setIndent('  ') // Match Prettier config
  ->setFinder($finder);
