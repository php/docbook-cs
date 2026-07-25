<?php

declare(strict_types=1);

namespace DocbookCS\Process;

final class ProcessException extends \RuntimeException
{
    public static function couldNotStart(): self
    {
        return new self('Could not start process.');
    }
}
