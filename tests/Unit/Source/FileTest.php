<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Source;

use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(File::class),
    CoversClass(Line::class),
]
final class FileTest extends TestCase
{
    #[Test]
    public function itRepresentsLinesAndTheirEndings(): void
    {
        $file = new File('file.xml', "one\r\ntwo\nthree\rfour");
        $lines = iterator_to_array($file->lines());

        self::assertEquals([
            new Line(1, 'one', "\r\n", 0),
            new Line(2, 'two', "\n", 5),
            new Line(3, 'three', "\r", 9),
            new Line(4, 'four', '', 15),
        ], $lines);
        self::assertSame([3, 8, 14, 19], array_map(
            static fn(Line $line): int => $line->offsetAfterContent(),
            $lines,
        ));
        self::assertSame([5, 9, 15, 19], array_map(
            static fn(Line $line): int => $line->offsetAfterLine(),
            $lines,
        ));
    }

    #[Test]
    public function itResolvesOffsetsToLineNumbers(): void
    {
        $file = new File('file.xml', "one\r\ntwo\nthree");

        self::assertSame(1, $file->lineNumberAtOffset(0));
        self::assertSame(1, $file->lineNumberAtOffset(3));
        self::assertSame(1, $file->lineNumberAtOffset(4));
        self::assertSame(2, $file->lineNumberAtOffset(5));
        self::assertSame(2, $file->lineNumberAtOffset(8));
        self::assertSame(3, $file->lineNumberAtOffset(9));
        self::assertSame(3, $file->lineNumberAtOffset(14));
    }

    #[Test]
    public function itIncludesAnEmptyLineAfterTheFinalLineEnding(): void
    {
        $file = new File('file.xml', "one\n");

        self::assertEquals([
            new Line(1, 'one', "\n", 0),
            new Line(2, '', '', 4),
        ], iterator_to_array($file->lines()));

        self::assertSame(2, $file->lineNumberAtOffset(4));
    }

    #[Test]
    public function itCreatesANewRevisionWithoutChangingTheCurrentRevision(): void
    {
        $initialFile = new File('file.xml', '<root/>');

        $modifiedFile = $initialFile->withContent('<root fixed="fixed"/>');

        self::assertSame('<root/>', $initialFile->content);
        self::assertSame('file.xml', $modifiedFile->path);
        self::assertSame('<root fixed="fixed"/>', $modifiedFile->content);
    }

    #[Test]
    public function itReusesTheCurrentRevisionForUnchangedContent(): void
    {
        $file = new File('file.xml', '<root/>');

        self::assertSame($file, $file->withContent('<root/>'));
    }

    #[Test]
    public function itRejectsOffsetsOutsideTheSource(): void
    {
        $file = new File('file.xml', 'one');

        $this->expectException(\OutOfBoundsException::class);
        $file->lineNumberAtOffset(4);
    }
}
