<?php

declare(strict_types=1);

namespace DocbookCS\Source;

final class File
{
    /** @var non-empty-list<int>|null */
    private ?array $lineBeginOffsets = null;

    public function __construct(
        public readonly string $path,
        public readonly string $content,
    ) {
    }

    /** @return \Generator<int, Line> */
    public function lines(): \Generator
    {
        $sourceLength = strlen($this->content);
        $lineBeginOffset = 0;
        $lineNumber = 1;

        while (true) {
            $line = $this->createLineAtOffset($lineNumber, $lineBeginOffset);
            yield $line;

            if ($line->offsetAfterContent() === $sourceLength) {
                return;
            }

            $lineBeginOffset = $line->offsetAfterLine();
            $lineNumber++;
        }
    }

    /** @throws \OutOfBoundsException if the offset lies outside the source */
    public function lineNumberAtOffset(int $offset): int
    {
        return $this->lineIndexAtOffset($offset, $this->lineBeginOffsets()) + 1;
    }

    public function withContent(string $content): self
    {
        return $content === $this->content
            ? $this
            : new self($this->path, $content);
    }

    /** @return non-empty-list<int> */
    private function lineBeginOffsets(): array
    {
        if ($this->lineBeginOffsets !== null) {
            return $this->lineBeginOffsets;
        }

        $lineBeginOffsets = [0];
        $sourceLength = strlen($this->content);
        $lineBeginOffset = 0;

        while ($lineBeginOffset < $sourceLength) {
            $lineLength = strcspn($this->content, "\r\n", $lineBeginOffset);
            $lineEndingOffset = $lineBeginOffset + $lineLength;

            if ($lineEndingOffset === $sourceLength) {
                break;
            }

            $lineBeginOffset = $lineEndingOffset + (
                $this->content[$lineEndingOffset] === "\r"
                && ($this->content[$lineEndingOffset + 1] ?? null) === "\n"
                    ? 2
                    : 1
            );
            $lineBeginOffsets[] = $lineBeginOffset;
        }

        return $this->lineBeginOffsets = $lineBeginOffsets;
    }

    /**
     * @param non-empty-list<int> $lineBeginOffsets
     * @throws \OutOfBoundsException if the offset lies outside the source
     */
    private function lineIndexAtOffset(int $offset, array $lineBeginOffsets): int
    {
        $sourceLength = strlen($this->content);
        if ($offset < 0 || $offset > $sourceLength) {
            throw new \OutOfBoundsException(
                sprintf('Source offset %d is outside the valid range 0..%d.', $offset, $sourceLength),
            );
        }

        $low = 0;
        $high = count($lineBeginOffsets) - 1;

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);

            if ($lineBeginOffsets[$middle] <= $offset) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return $low;
    }

    private function createLineAtOffset(int $lineNumber, int $lineBeginOffset): Line
    {
        $sourceLength = strlen($this->content);
        $lineLength = strcspn($this->content, "\r\n", $lineBeginOffset);
        $offsetAfterContent = $lineBeginOffset + $lineLength;

        return new Line(
            number: $lineNumber,
            content: substr($this->content, $lineBeginOffset, $lineLength),
            lineEnding: $offsetAfterContent < $sourceLength
                ? $this->lineEndingAt($offsetAfterContent)
                : '',
            beginOffset: $lineBeginOffset,
        );
    }

    private function lineEndingAt(int $offset): string
    {
        if ($this->content[$offset] === "\r" && ($this->content[$offset + 1] ?? null) === "\n") {
            return "\r\n";
        }

        return $this->content[$offset];
    }
}
