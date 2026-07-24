<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Violation;

use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(SourceRange::class),
    CoversClass(Violation::class),
    //
    UsesClass(File::class),
    UsesClass(Line::class),
]
final class AffectedRangesTest extends TestCase
{
    #[Test]
    public function itCreatesSourceRangesFromFileOffsets(): void
    {
        $range = SourceRange::fromFile(
            new File('file.xml', "first\nsecond\n"),
            6,
            12,
        );

        self::assertSame(2, $range->line);
        self::assertSame(6, $range->beginOffset);
        self::assertSame(12, $range->untilOffset);
        self::assertSame('second', $range->content);
    }

    #[Test]
    public function itRejectsViolationsWithoutAffectedRanges(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Bypass the static non-empty-list contract to exercise runtime validation.
        new \ReflectionClass(Violation::class)->newInstanceArgs(['Test', 'file.xml', 'Message', []]);
    }

    #[Test]
    public function itRejectsUnorderedOrOverlappingAffectedRanges(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Violation('Test', 'file.xml', 'Message', [
            new SourceRange(1, 10, 20),
            new SourceRange(1, 15, 25),
        ]);
    }

    #[Test, DataProvider('invalidSourceRanges')]
    public function itRejectsInvalidSourceRanges(int $line, int $beginOffset, int $untilOffset, ?string $content): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SourceRange($line, $beginOffset, $untilOffset, $content);
    }

    /** @return iterable<string, array{int, int, int, string|null}> */
    public static function invalidSourceRanges(): iterable
    {
        yield 'negative line' => [-1, 0, 0, null];
        yield 'negative begin offset' => [1, -1, 0, null];
        yield 'end before beginning' => [1, 2, 1, null];
        yield 'content length differs' => [1, 0, 2, 'x'];
    }

    #[Test]
    public function itCreatesFileReadFailureViolations(): void
    {
        $violation = Violation::fromFileReadFailure('file.xml');

        self::assertSame('DocbookCS.Internal', $violation->sniffCode);
        self::assertSame('file.xml', $violation->filePath);
        self::assertSame('Could not read file.', $violation->message);
        self::assertSame(Severity::ERROR, $violation->severity);
        self::assertEquals(new SourceRange(0, 0, 0), $violation->rangeOne());
    }

    #[Test]
    public function itCreatesXmlParseErrorViolations(): void
    {
        $error = new \LibXMLError();
        $error->message = "Invalid XML\n";
        $error->line = 7;

        $violation = Violation::fromXmlParseError('file.xml', $error);

        self::assertSame('DocbookCS.Internal', $violation->sniffCode);
        self::assertSame('file.xml', $violation->filePath);
        self::assertSame('XML parse error: Invalid XML', $violation->message);
        self::assertSame(Severity::ERROR, $violation->severity);
        self::assertEquals(new SourceRange(7, 0, 0), $violation->rangeOne());
    }

    #[Test]
    public function itCreatesXmlParseErrorViolationsWithoutLibxmlDetails(): void
    {
        $violation = Violation::fromXmlParseError('file.xml', null);

        self::assertSame('XML parse error: unknown', $violation->message);
        self::assertEquals(new SourceRange(0, 0, 0), $violation->rangeOne());
    }
}
