<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\Fixer;

/**
 * @template TFixerData = mixed
 * @extends SniffInterface<TFixerData>
 */
interface Fixable extends SniffInterface
{
    /** @return class-string<Fixer<TFixerData>> */
    public static function getFixerClassName(): string;
}
