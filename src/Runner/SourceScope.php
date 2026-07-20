<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Diff\FileChange;
use DocbookCS\Fix\Fix;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\Violation;

final readonly class SourceScope
{
    /**
     * Null means the whole source file; an empty list means no source range.
     *
     * @param list<array{int, int}>|null $ranges
     */
    private function __construct(private ?array $ranges)
    {
    }

    public static function wholeFile(): self
    {
        return new self(null);
    }

    public static function fromFileChange(File $file, FileChange $fileChange): self
    {
        $selectedLines = array_fill_keys($fileChange->addedLineNumbers, true);
        $ranges = [];
        $lineBeginOffsets = [];
        $lastLineNumber = 1;

        foreach ($file->lines() as $line) {
            $lineBeginOffsets[$line->number] = $line->beginOffset;
            $lastLineNumber = $line->number;

            if (isset($selectedLines[$line->number])) {
                $ranges[] = [$line->beginOffset, $line->offsetAfterLine()];
            }
        }

        foreach ($fileChange->deletionAnchors as $lineNumber) {
            $offset = $lineBeginOffsets[$lineNumber]
                ?? ($lineNumber === $lastLineNumber + 1 ? strlen($file->content) : null);

            if ($offset === null) {
                continue;
            }

            $ranges[] = [$offset, $offset];
        }

        usort($ranges, static fn(array $a, array $b): int => $a <=> $b);

        $normalisedRanges = [];
        foreach ($ranges as [$beginOffset, $untilOffset]) {
            self::appendRange($normalisedRanges, $beginOffset, $untilOffset);
        }

        return new self($normalisedRanges);
    }

    public function isWholeFile(): bool
    {
        return $this->ranges === null;
    }

    /** @return list<int> */
    public function lineNumbers(File $file): array
    {
        if ($this->ranges === null) {
            return array_map(static fn(Line $line): int => $line->number, iterator_to_array($file->lines(), false));
        }

        $lineNumbers = [];
        $rangeIndex = 0;
        $rangeCount = count($this->ranges);

        foreach ($file->lines() as $line) {
            while ($rangeIndex < $rangeCount && self::endsBefore($this->ranges[$rangeIndex], $line->beginOffset)) {
                $rangeIndex++;
            }

            if ($rangeIndex === $rangeCount) {
                break;
            }

            [$beginOffset, $untilOffset] = $this->ranges[$rangeIndex];
            if (self::overlaps($beginOffset, $untilOffset, $line->beginOffset, $line->offsetAfterLine())) {
                $lineNumbers[] = $line->number;
            }
        }

        return $lineNumbers;
    }

    public function includes(Violation $violation): bool
    {
        if ($this->ranges === null) {
            return true;
        }

        /** @noinspection PhpLoopCanBeConvertedToArrayAnyInspection */
        foreach ($this->ranges as [$beginOffset, $untilOffset]) {
            if (self::overlaps($beginOffset, $untilOffset, $violation->beginOffset, $violation->untilOffset)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Fix> $fixes */
    public function after(array $fixes): self
    {
        if ($this->ranges === null || $this->ranges === [] || $fixes === []) {
            return $this;
        }

        usort(
            $fixes,
            static fn(Fix $a, Fix $b): int => [
                $a->beginOffset,
                $a->untilOffset,
            ] <=> [
                $b->beginOffset,
                $b->untilOffset,
            ],
        );

        $ranges = [];
        foreach ($this->ranges as [$beginOffset, $untilOffset]) {
            self::appendRange(
                $ranges,
                self::mapOffset($beginOffset, $fixes, endBoundary: false),
                self::mapOffset(
                    $untilOffset,
                    $fixes,
                    endBoundary: true,
                    includeInsertionAtOffset: $beginOffset === $untilOffset,
                ),
            );
        }

        return new self($ranges);
    }

    /** @param array{int, int} $range */
    private static function endsBefore(array $range, int $offset): bool
    {
        [$beginOffset, $untilOffset] = $range;

        return $untilOffset < $offset
            || ($untilOffset === $offset && $beginOffset !== $untilOffset);
    }

    /** @param list<array{int, int}> $ranges */
    private static function appendRange(array &$ranges, int $beginOffset, int $untilOffset): void
    {
        $lastIndex = count($ranges) - 1;
        if ($lastIndex >= 0 && $beginOffset <= $ranges[$lastIndex][1]) {
            $ranges[$lastIndex][1] = max($ranges[$lastIndex][1], $untilOffset);
            return;
        }

        $ranges[] = [$beginOffset, $untilOffset];
    }

    /** @param list<Fix> $fixes */
    private static function mapOffset(
        int $offset,
        array $fixes,
        bool $endBoundary,
        bool $includeInsertionAtOffset = false,
    ): int {
        $shift = 0;

        foreach ($fixes as $fix) {
            $isInsertion = $fix->beginOffset === $fix->untilOffset;

            if (
                $fix->untilOffset < $offset
                || (!$isInsertion && $fix->untilOffset === $offset)
                || ($includeInsertionAtOffset && $isInsertion && $fix->beginOffset === $offset)
            ) {
                $shift += strlen($fix->replacement) - ($fix->untilOffset - $fix->beginOffset);
                continue;
            }

            if ($fix->beginOffset < $offset && $fix->untilOffset > $offset) {
                return $fix->beginOffset + $shift + ($endBoundary ? strlen($fix->replacement) : 0);
            }

            if ($fix->beginOffset >= $offset) {
                break;
            }
        }

        return $offset + $shift;
    }

    private static function overlaps(int $aBegin, int $aUntil, int $bBegin, int $bUntil): bool
    {
        if ($aBegin === $aUntil) {
            return $bBegin === $bUntil
                ? $aBegin === $bBegin
                : $aBegin >= $bBegin && $aBegin < $bUntil;
        }

        if ($bBegin === $bUntil) {
            return $bBegin >= $aBegin && $bBegin < $aUntil;
        }

        return max($aBegin, $bBegin) < min($aUntil, $bUntil);
    }
}
