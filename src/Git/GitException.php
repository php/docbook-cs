<?php

declare(strict_types=1);

namespace DocbookCS\Git;

use DocbookCS\Process\ProcessException;

final class GitException extends \RuntimeException
{
    public static function processFailed(ProcessException $exception): self
    {
        return new self($exception->getMessage(), 0, $exception);
    }

    public static function commandFailed(string $message, string $detail): self
    {
        return new self($detail !== '' ? "$message $detail" : $message);
    }

    public static function localMasterNotFound(): self
    {
        return new self('Could not find local master branch for the contribution diff.');
    }

    public static function mergeBaseNotFound(string $reference): self
    {
        return new self(sprintf('Unclear where HEAD branched from %s.', $reference));
    }
}
