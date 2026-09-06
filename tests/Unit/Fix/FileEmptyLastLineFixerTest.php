<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\Fixer\FileEmptyLastLineFixer;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixResult;
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
    CoversClass(FileEmptyLastLineFixer::class),
    CoversClass(FileEmptyLastLineSniffer::class),
    CoversClass(Fix::class),
    CoversClass(FixApplier::class),
    CoversClass(FixResult::class),
    //
    UsesClass(File::class),
    UsesClass(FixPlan::class),
    UsesClass(SourceRange::class),
    UsesClass(Violation::class),
]
final class FileEmptyLastLineFixerTest extends TestCase
{
    #[Test]
    public function itTreatsTheCanonicalEndingAsANoOp(): void
    {
        $content = "<root/>\n";
        $source = new File('file.xml', $content);
        $range = new SourceRange(1, strlen($content) - 1, strlen($content), "\n");
        $violation = new Violation(
            FileEmptyLastLineSniffer::getCode(),
            $source->path,
            'violation.',
            [$range],
        );

        $fix = new FileEmptyLastLineFixer()->process($violation);
        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame($content, $result->file->content);
        self::assertSame(0, $result->applied);
        self::assertSame(1, $result->skipped);
    }

    #[Test, DataProvider('nonCompliantEndings')]
    public function itLeavesExactlyOneLfEmptyLastLine(string $content, string $expected): void
    {
        $source = new File('file.xml', $content);
        $document = new \DOMDocument();
        $document->loadXML($content);
        $sniffer = new FileEmptyLastLineSniffer();
        $violation = $sniffer->process($document, $source)[0];

        $fix = new FileEmptyLastLineFixer()->process($violation);
        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame($expected, $result->file->content);
        self::assertSame(1, $result->applied);
        self::assertSame([], $sniffer->process($document, $result->file));
    }

    /** @return iterable<string, array{string, string}> */
    public static function nonCompliantEndings(): iterable
    {
        yield 'missing ending' => ['<root/>', "<root/>\n"];
        yield 'after line feed content' => ["<root>\n</root>", "<root>\n</root>\n"];
        yield 'after carriage return and line feed content' => ["<root>\r\n</root>", "<root>\r\n</root>\n"];
        yield 'after carriage return content' => ["<root>\r</root>", "<root>\r</root>\n"];
        yield 'extra line feed' => ["<root/>\n\n", "<root/>\n"];
        yield 'carriage return and line feed' => ["<root/>\r\n", "<root/>\n"];
        yield 'carriage return' => ["<root/>\r", "<root/>\n"];
        yield 'extra carriage return and line feed' => ["<root/>\r\n\r\n", "<root/>\n"];
        yield 'extra carriage return' => ["<root/>\r\r", "<root/>\n"];
        yield 'multiple mixed extra endings' => ["<root/>\r\n\n\r", "<root/>\n"];
    }
}
