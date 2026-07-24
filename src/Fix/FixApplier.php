<?php

declare(strict_types=1);

namespace DocbookCS\Fix;

use DocbookCS\Source\File;

final class FixApplier
{
    /**
     * @param list<Fix|FixPlan> $fixes
     */
    public function apply(File $file, array $fixes): FixResult
    {
        if ($fixes === []) {
            return new FixResult($file);
        }

        $plans = [];
        foreach ($fixes as $index => $fix) {
            $plan = $fix instanceof FixPlan ? $fix : new FixPlan($fix);
            $plans[] = [
                'index' => $index,
                'plan' => $plan,
            ];
        }

        usort($plans, static function (array $a, array $b): int {
            $offsetComparison = $a['plan']->firstOffset() <=> $b['plan']->firstOffset();

            return $offsetComparison !== 0
                ? $offsetComparison
                : $a['index'] <=> $b['index'];
        });

        /** @var list<Fix> $acceptedFixes */
        $acceptedFixes = [];
        $acceptedPlans = 0;
        $skipped = 0;

        $content = $file->content;
        $length = strlen($content);

        foreach ($plans as ['plan' => $plan]) {
            if (!$this->canApply($file, $length, $plan, $acceptedFixes)) {
                $skipped++;
                continue;
            }

            if (!$this->changesContent($content, $plan)) {
                $skipped++;
                continue;
            }

            foreach ($plan->fixes as $fix) {
                $this->insertFix($acceptedFixes, $fix);
            }

            $acceptedPlans++;
        }

        $fixedContent = '';
        $sourceOffset = 0;

        foreach ($acceptedFixes as $fix) {
            $fixedContent .= substr($content, $sourceOffset, $fix->beginOffset - $sourceOffset);
            $fixedContent .= $fix->replacement;
            $sourceOffset = $fix->untilOffset;
        }

        $content = $fixedContent . substr($content, $sourceOffset);

        return new FixResult(
            file: $file->withContent($content),
            applied: $acceptedPlans,
            skipped: $skipped,
            appliedFixes: $acceptedFixes,
        );
    }

    /**
     * @param list<Fix> $acceptedFixes
     */
    private function canApply(File $file, int $contentLength, FixPlan $plan, array $acceptedFixes): bool
    {
        $content = $file->content;
        $first = $plan->fixes[0];
        $previous = null;

        foreach ($plan->fixes as $fix) {
            if (
                $fix->filePath !== $file->path
                || $fix->filePath !== $first->filePath
                || $fix->sniffCode !== $first->sniffCode
                || $fix->beginOffset < 0
                || $fix->untilOffset < $fix->beginOffset
                || $fix->untilOffset > $contentLength
                || ($previous !== null && self::overlaps($previous, $fix))
                || $this->conflictsWithAcceptedFix($fix, $acceptedFixes)
            ) {
                return false;
            }

            $currentContent = substr($content, $fix->beginOffset, $fix->untilOffset - $fix->beginOffset);

            if ($fix->expectedContent !== null && $currentContent !== $fix->expectedContent) {
                return false;
            }

            $previous = $fix;
        }

        return true;
    }

    private function changesContent(string $content, FixPlan $plan): bool
    {
        foreach ($plan->fixes as $fix) {
            $currentContent = substr($content, $fix->beginOffset, $fix->untilOffset - $fix->beginOffset);

            if ($currentContent !== $fix->replacement) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Fix> $acceptedFixes
     */
    private function conflictsWithAcceptedFix(Fix $fix, array $acceptedFixes): bool
    {
        $index = $this->insertionIndex($acceptedFixes, $fix);

        return ($index > 0 && self::overlaps($acceptedFixes[$index - 1], $fix))
            || ($index < count($acceptedFixes) && self::overlaps($acceptedFixes[$index], $fix));
    }

    /**
     * @param list<Fix> $fixes
     */
    private function insertFix(array &$fixes, Fix $fix): void
    {
        $lastIndex = count($fixes) - 1;
        if ($lastIndex < 0 || self::compare($fixes[$lastIndex], $fix) < 0) {
            $fixes[] = $fix;
            return;
        }

        array_splice($fixes, $this->insertionIndex($fixes, $fix), 0, [$fix]);
    }

    /**
     * @param list<Fix> $fixes
     */
    private function insertionIndex(array $fixes, Fix $fix): int
    {
        $low = 0;
        $high = count($fixes);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            if (self::compare($fixes[$middle], $fix) < 0) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }

    private static function compare(Fix $a, Fix $b): int
    {
        return [
            $a->beginOffset,
            $a->untilOffset,
        ] <=> [
            $b->beginOffset,
            $b->untilOffset,
        ];
    }

    private static function overlaps(Fix $a, Fix $b): bool
    {
        $aIsInsertion = $a->beginOffset === $a->untilOffset;
        $bIsInsertion = $b->beginOffset === $b->untilOffset;

        if ($aIsInsertion && $bIsInsertion) {
            return $a->beginOffset === $b->beginOffset;
        }

        if ($aIsInsertion) {
            return $a->beginOffset > $b->beginOffset && $a->beginOffset < $b->untilOffset;
        }

        if ($bIsInsertion) {
            return $b->beginOffset > $a->beginOffset && $b->beginOffset < $a->untilOffset;
        }

        return $a->beginOffset < $b->untilOffset && $b->beginOffset < $a->untilOffset;
    }
}
