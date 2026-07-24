<?php

declare(strict_types=1);

namespace DocbookCS\Violation;

use DocbookCS\Source\File;

final readonly class SourceRange
{
    /**
     * @throws \InvalidArgumentException if the source range is inconsistent
     * @throws \OutOfBoundsException if the begin offset lies outside the source
     */
    public static function fromFile(File $file, int $beginOffset, int $untilOffset): self
    {
        return new self(
            line: $file->lineAtOffset($beginOffset)->number,
            beginOffset: $beginOffset,
            untilOffset: $untilOffset,
            content: substr($file->content, $beginOffset, $untilOffset - $beginOffset),
        );
    }

    /** @throws \InvalidArgumentException if the source range is inconsistent */
    public function __construct(
        public int $line,
        public int $beginOffset,
        public int $untilOffset,
        public ?string $content = null,
    ) {
        if ($line < 0) {
            throw new \InvalidArgumentException('A source range line cannot be negative.');
        }

        if ($beginOffset < 0) {
            throw new \InvalidArgumentException('A source range begin offset cannot be negative.');
        }

        if ($untilOffset < $beginOffset) {
            throw new \InvalidArgumentException('A source range cannot end before it begins.');
        }

        if ($content !== null && strlen($content) !== $untilOffset - $beginOffset) {
            throw new \InvalidArgumentException('Source range content must match the range length.');
        }
    }
}
