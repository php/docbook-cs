<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Diff\FileChange;
use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\Fixer\SimparaFixer;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixResult;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Runner\XmlFixRunner;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunScope;
use DocbookCS\Runner\ViolationScopeFilter;
use DocbookCS\Runner\XmlSniffRunner;
use DocbookCS\Sniff\SimparaSniff;
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
    CoversClass(RunScope::class),
    CoversClass(SimparaSniff::class),
    CoversClass(XmlFileProcessor::class),
    CoversClass(XmlSniffRunner::class),
    //
    UsesClass(EntityExpansionMarker::class),
    UsesClass(EntityPreprocessor::class),
    UsesClass(File::class),
    UsesClass(FileChange::class),
    UsesClass(FileReport::class),
    UsesClass(Fix::class),
    UsesClass(FixApplier::class),
    UsesClass(FixPlan::class),
    UsesClass(FixResult::class),
    UsesClass(Line::class),
    UsesClass(Report::class),
    UsesClass(RunMode::class),
    UsesClass(SimparaFixer::class),
    UsesClass(SourceRange::class),
    UsesClass(Violation::class),
    UsesClass(ViolationScopeFilter::class),
    UsesClass(XmlFixRunner::class),
    UsesClass(XmlParser::class),
]
final class SourceRangeScopeTest extends TestCase
{
    #[Test]
    public function itAppliesEveryRangeOfAViolationIntersectingAChangedLine(): void
    {
        $source = <<<'XML'
<root>
<para>
Text
</para>
</root>
XML;
        $expected = <<<'XML'
<root>
<simpara>
Text
</simpara>
</root>
XML;
        $filePath = tempnam(sys_get_temp_dir(), 'docbook-cs-');
        self::assertIsString($filePath);
        file_put_contents($filePath, $source);

        try {
            $processor = new XmlFileProcessor(new XmlSniffRunner(RunMode::Fix, [
                new SimparaSniff(),
            ]));

            $fixedFile = $processor->process(
                $file = new File($filePath, $source),
                $fileReport = new FileReport($filePath),
                RunScope::fromFileAndFileChange($file, new FileChange($filePath, [3])),
            );
            self::assertNotNull($fixedFile);
            file_put_contents($filePath, $fixedFile->content);

            self::assertSame($expected, file_get_contents($filePath));
            self::assertFalse($fileReport->hasFinalViolations());
        } finally {
            @unlink($filePath);
        }
    }

    #[Test]
    public function itFixesAViolationCausedByADeletedLine(): void
    {
        $source = <<<'XML'
<root>
<para>
Text
</para>
</root>
XML;
        $expected = <<<'XML'
<root>
<simpara>
Text
</simpara>
</root>
XML;
        $processor = new XmlFileProcessor(new XmlSniffRunner(RunMode::Fix, [
            new SimparaSniff(),
        ]));

        $fixedFile = $processor->process(
            $file = new File('file.xml', $source),
            $fileReport = new FileReport('file.xml'),
            RunScope::fromFileAndFileChange($file, new FileChange('file.xml', [], deletionAnchors: [3])),
        );

        self::assertNotNull($fixedFile);
        self::assertSame($expected, $fixedFile->content);
        self::assertFalse($fileReport->hasFinalViolations());
    }
}
