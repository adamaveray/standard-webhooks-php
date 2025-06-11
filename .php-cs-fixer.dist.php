<?php
declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

require_once __DIR__ . '/vendor/autoload.php';

$finder = (new Finder())->exclude(['node_modules', 'vendor'])->in(__DIR__);

return (new Config())
  ->setRules([
    '@PER-CS' => true,
    '@PHP84Migration' => true,
  ])
  ->setFinder($finder);
