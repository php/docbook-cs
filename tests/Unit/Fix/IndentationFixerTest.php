<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Diff\FileChange;
use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\Fixer\IndentationFixer;
use DocbookCS\Fix\FixerException;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixResult;
use DocbookCS\IndentationAnalyzer;
use DocbookCS\Report\FileReport;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunScope;
use DocbookCS\Runner\ViolationScopeFilter;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Runner\XmlFixRunner;
use DocbookCS\Runner\XmlSniffRunner;
use DocbookCS\Sniff\IndentationSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use DocbookCS\Xml\XmlParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(IndentationFixer::class),
    //
    UsesClass(EntityPreprocessor::class),
    UsesClass(File::class),
    UsesClass(FileChange::class),
    UsesClass(Fix::class),
    UsesClass(FixApplier::class),
    UsesClass(FixPlan::class),
    UsesClass(FixResult::class),
    UsesClass(FixerException::class),
    UsesClass(FileReport::class),
    UsesClass(IndentationAnalyzer::class),
    UsesClass(IndentationSniff::class),
    UsesClass(Line::class),
    UsesClass(RunMode::class),
    UsesClass(RunScope::class),
    UsesClass(SourceRange::class),
    UsesClass(ViolationScopeFilter::class),
    UsesClass(Violation::class),
    UsesClass(XmlFileProcessor::class),
    UsesClass(XmlFixRunner::class),
    UsesClass(XmlParser::class),
    UsesClass(XmlSniffRunner::class),
]
final class IndentationFixerTest extends TestCase
{
    #[Test]
    public function itFixesIssue47WithoutChangingVerbatimContent(): void
    {
        $source = new File('issue_47.xml', $this->fixture('issue_47.xml'));
        $fileReport = new FileReport($source->path);

        $fixedFile = $this->processor()->process(
            $source,
            $fileReport,
            RunScope::fromFileAndFileChange($source, null),
        );

        self::assertNotNull($fixedFile);
        self::assertSame($this->fixture('issue_47.fixed.xml'), $fixedFile->content);
        self::assertSame(9, $fileReport->getFoundViolationCount());
        self::assertSame(9, $fileReport->getAppliedFixesCount());
        self::assertSame(1, $fileReport->fixingPasses);
        self::assertFalse($fileReport->hasFinalViolations());
    }

    #[Test]
    public function itReplacesIndentationWithTheProvidedExpectedDepth(): void
    {
        $violation = new Violation(
            IndentationSniff::getCode(),
            'file.xml',
            'Expected indentation.',
            [new SourceRange(2, 7, 8, "\t")],
            fixerData: 3,
        );

        $fix = new IndentationFixer()->process($violation);

        self::assertEquals(new Fix(
            filePath: 'file.xml',
            beginOffset: 7,
            untilOffset: 8,
            replacement: '   ',
            sniffCode: IndentationSniff::getCode(),
            expectedContent: "\t",
        ), $fix);
    }

    #[Test]
    public function itUsesUnchangedStructureWhenFixingAChangedLine(): void
    {
        $content = "<root>\n <section>\n<para />\n   </section>\n</root>";
        $source = new File('file.xml', $content);
        $fileReport = new FileReport($source->path);

        $fixedFile = $this->processor()->process(
            $source,
            $fileReport,
            RunScope::fromFileAndFileChange(
                $source,
                new FileChange($source->path, [3]),
            ),
        );

        self::assertNotNull($fixedFile);
        self::assertSame(
            "<root>\n <section>\n  <para />\n   </section>\n</root>",
            $fixedFile->content,
        );
        self::assertSame(1, $fileReport->getAppliedFixesCount());
        self::assertFalse($fileReport->hasFinalViolations());
    }

    #[Test, DataProvider('invalidFixerData')]
    public function itRejectsInvalidFixerData(mixed $fixerData): void
    {
        $this->expectException(FixerException::class);

        $violation = new Violation(
            IndentationSniff::getCode(),
            'file.xml',
            'Expected indentation.',
            [new SourceRange(1, 0, 1, "\t")],
            fixerData: $fixerData,
        );

        // Bypass the PHPStan generic contract to exercise the runtime boundary.
        new \ReflectionMethod(IndentationFixer::class, 'process')->invoke(new IndentationFixer(), $violation);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidFixerData(): iterable
    {
        yield 'not an integer' => ['1'];
        yield 'negative integer' => [-1];
    }

    #[Test]
    public function itRejectsMultipleAffectedRanges(): void
    {
        $this->expectException(FixerException::class);

        new IndentationFixer()->process(new Violation(
            IndentationSniff::getCode(),
            'file.xml',
            'Expected indentation.',
            [
                new SourceRange(1, 0, 1, "\t"),
                new SourceRange(2, 2, 3, "\t"),
            ],
            fixerData: 1,
        ));
    }

    #[Test]
    public function itRejectsAnAlreadyCorrectReplacement(): void
    {
        $this->expectException(FixerException::class);

        new IndentationFixer()->process(new Violation(
            IndentationSniff::getCode(),
            'file.xml',
            'Expected indentation.',
            [new SourceRange(1, 0, 1, ' ')],
            fixerData: 1,
        ));
    }

    private function fixture(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/indentation/' . $name);
        self::assertIsString($content);

        return $content;
    }

    private function processor(): XmlFileProcessor
    {
        return new XmlFileProcessor(new XmlSniffRunner(
            RunMode::Fix,
            [new IndentationSniff()],
        ));
    }
}
