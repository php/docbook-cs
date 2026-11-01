<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\Fixer\IndentationFixer;
use DocbookCS\Fix\Fixer\TrailingWhitespaceFixer;
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
use DocbookCS\Sniff\TrailingWhitespaceSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use DocbookCS\Xml\XmlParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(Fix::class),
    CoversClass(FixApplier::class),
    CoversClass(FixResult::class),
    CoversClass(IndentationFixer::class),
    CoversClass(IndentationSniff::class),
    CoversClass(TrailingWhitespaceFixer::class),
    CoversClass(TrailingWhitespaceSniff::class),
    //
    UsesClass(EntityPreprocessor::class),
    UsesClass(File::class),
    UsesClass(FileReport::class),
    UsesClass(FixPlan::class),
    UsesClass(IndentationAnalyzer::class),
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
final class WhitespaceConcernFixersTest extends TestCase
{
    #[Test]
    public function itFixesIndependentWhitespaceConcernsTogether(): void
    {
        $content = "<root> \n \t<tag/>  \n</root>";
        $source = new File('file.xml', $content);
        $fileReport = new FileReport($source->path);

        $fixedFile = new XmlFileProcessor(new XmlSniffRunner(
            RunMode::Fix,
            [new TrailingWhitespaceSniff(), new IndentationSniff()],
        ))->process(
            $source,
            $fileReport,
            RunScope::fromFileAndFileChange($source, null),
        );

        self::assertNotNull($fixedFile);
        self::assertSame("<root>\n <tag/>\n</root>", $fixedFile->content);
        self::assertSame(3, $fileReport->getFoundViolationCount());
        self::assertSame(3, $fileReport->getAppliedFixesCount());
        self::assertSame(1, $fileReport->fixingPasses);
        self::assertFalse($fileReport->hasFinalViolations());
    }
}
