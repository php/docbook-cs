<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Violation;

use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(SourceRange::class),
    CoversClass(Violation::class),
]
final class AffectedRangesTest extends TestCase
{
    #[Test]
    public function itDefaultsToTheExistingFindingRange(): void
    {
        $violation = new Violation('Test', 'file.xml', 3, 10, 20, 'Message', 'content');

        self::assertEquals([new SourceRange(3, 10, 20)], $violation->affectedRanges);
    }

    #[Test]
    public function relatedRangesDoNotChangeTheExistingFinding(): void
    {
        $ranges = [
            new SourceRange(3, 11, 15),
            new SourceRange(8, 40, 44),
        ];
        $violation = new Violation(
            sniffCode: 'Test',
            filePath: 'file.xml',
            line: 3,
            beginOffset: 10,
            untilOffset: 45,
            message: 'Message',
            content: '<para>content</para>',
            affectedRanges: $ranges,
        );

        self::assertSame(10, $violation->beginOffset);
        self::assertSame(45, $violation->untilOffset);
        self::assertSame('<para>content</para>', $violation->content);
        self::assertSame($ranges, $violation->affectedRanges);
    }
}
