<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\Fixer;

interface Fixable extends SniffInterface
{
    /** @return class-string<Fixer> */
    public static function fixerClassName(): string;
}
