<?php

declare(strict_types=1);

namespace DocbookCS\Source;

final readonly class Line
{
    public function __construct(
        public int $number,
        public string $content,
        public string $lineEnding,
        public int $beginOffset,
    ) {
    }

    public function offsetAfterContent(): int
    {
        return $this->beginOffset + strlen($this->content);
    }

    public function offsetAfterLine(): int
    {
        return $this->offsetAfterContent() + strlen($this->lineEnding);
    }
}
