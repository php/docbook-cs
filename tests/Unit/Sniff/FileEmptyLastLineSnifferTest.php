<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Fix\Fixer\FileEmptyLastLineFixer;
use DocbookCS\Sniff\FileEmptyLastLineSniffer;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(FileEmptyLastLineSniffer::class),
    //
    UsesClass(File::class),
    UsesClass(SourceRange::class),
    UsesClass(Violation::class),
]
final class FileEmptyLastLineSnifferTest extends TestCase
{
    #[Test]
    public function itProvidesItsCodeAndFixer(): void
    {
        self::assertSame('DocbookCS.FileEmptyLastLine', FileEmptyLastLineSniffer::getCode());
        self::assertSame(FileEmptyLastLineFixer::class, FileEmptyLastLineSniffer::getFixerClassName());
    }

    #[Test]
    public function itAllowsExactlyOneLfEmptyLastLine(): void
    {
        self::assertSame([], $this->process("<root/>\n"));
    }

    #[Test, DataProvider('missingEndings')]
    public function itReportsAnUnterminatedLastLine(string $content, string $affectedContent): void
    {
        $violation = $this->process($content)[0];
        $beginOffset = strlen($content) - strlen($affectedContent);

        self::assertSame('DocbookCS.FileEmptyLastLine', $violation->sniffCode);
        self::assertSame('File must end with exactly one empty (LF) line.', $violation->message);
        self::assertSame($affectedContent, $violation->rangeOne()->content);
        self::assertSame($beginOffset, $violation->rangeOne()->beginOffset);
        self::assertSame(strlen($content), $violation->rangeOne()->untilOffset);
        self::assertSame(2, $violation->rangeOne()->line);
    }

    #[Test]
    public function itReportsTheOnlyLineWhenItsEndingIsMissing(): void
    {
        $content = '<root/>';
        $violation = $this->process($content)[0];

        self::assertSame($content, $violation->rangeOne()->content);
        self::assertSame(0, $violation->rangeOne()->beginOffset);
        self::assertSame(strlen($content), $violation->rangeOne()->untilOffset);
        self::assertSame(1, $violation->rangeOne()->line);
    }

    #[Test, DataProvider('nonCanonicalEndings')]
    public function itReportsTheEntireNonCanonicalEnding(
        string $content,
        string $affectedContent,
        int $expectedLine,
    ): void {
        $violation = $this->process($content)[0];
        $beginOffset = strlen($content) - strlen($affectedContent);

        self::assertSame($affectedContent, $violation->rangeOne()->content);
        self::assertSame($beginOffset, $violation->rangeOne()->beginOffset);
        self::assertSame(strlen($content), $violation->rangeOne()->untilOffset);
        self::assertSame($expectedLine, $violation->rangeOne()->line);
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingEndings(): iterable
    {
        yield 'line feed file' => ["<root>\n</root>", '</root>'];
        yield 'carriage return and line feed file' => ["<root>\r\n</root>", '</root>'];
        yield 'carriage return file' => ["<root>\r</root>", '</root>'];
        yield 'line feed at byte zero' => ["\n<root/>", '<root/>'];
        yield 'carriage return and line feed at byte zero' => ["\r\n<root/>", '<root/>'];
        yield 'carriage return at byte zero' => ["\r<root/>", '<root/>'];
    }

    /** @return iterable<string, array{string, string, int}> */
    public static function nonCanonicalEndings(): iterable
    {
        yield 'carriage return and line feed' => ["<root/>\r\n", "\r\n", 1];
        yield 'carriage return' => ["<root/>\r", "\r", 1];
        yield 'line feeds' => ["<root/>\n\n", "\n\n", 1];
        yield 'carriage returns and line feeds' => ["<root/>\r\n\r\n", "\r\n\r\n", 1];
        yield 'carriage returns' => ["<root/>\r\r", "\r\r", 1];
        yield 'multiple mixed endings' => ["<root/>\r\n\n\r", "\r\n\n\r", 1];
        yield 'multiline file' => ["<root>\n</root>\r\n", "\r\n", 2];
    }

    /** @return list<Violation> */
    private function process(string $content): array
    {
        $document = new \DOMDocument();
        $document->loadXML($content);

        return new FileEmptyLastLineSniffer()->process($document, new File('file.xml', $content));
    }
}
