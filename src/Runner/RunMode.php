<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

enum RunMode
{
    case Fix;
    case Sniff;

    public static function fromFixFlag(bool $flag): RunMode
    {
        return $flag ? self::Fix : self::Sniff;
    }

    public function isFixMode(): bool
    {
        return $this === self::Fix;
    }
}
