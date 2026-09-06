<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

final class SniffException extends \RuntimeException
{
    public static function cannotIdentifyFileEnding(): self
    {
        return new self('Cannot identify file ending.');
    }
}
