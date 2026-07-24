<?php

declare(strict_types=1);

namespace DocbookCS\Fix;

final readonly class FixPlan
{
    /** @var non-empty-list<Fix> */
    public array $fixes;

    public function __construct(Fix $first, Fix ...$additionalFixes)
    {
        $fixes = [$first, ...$additionalFixes];

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

        $this->fixes = $fixes;
    }

    public function firstOffset(): int
    {
        return $this->fixes[0]->beginOffset;
    }
}
