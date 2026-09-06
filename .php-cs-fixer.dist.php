<?php

declare(strict_types=1);

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS3x0' => true,
        '@PHP8x4Migration' => true,
    ])
    ->setFinder(new PhpCsFixer\Finder()
        ->in(__DIR__ . '/src')
        ->append([
            __FILE__,
            __DIR__ . '/bin/docbook-cs',
        ]))
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache')
    ->setUnsupportedPhpVersionAllowed(true)
;
