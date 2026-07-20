<?php

declare(strict_types=1);

namespace DocbookCS\Violation;

final readonly class SourceRange
{
    public function __construct(
        public int $line,
        public int $beginOffset,
        public int $untilOffset,
    ) {
    }
}
