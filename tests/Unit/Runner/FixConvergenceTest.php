<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Diff\FileChange;
use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\Fixer\ExceptionNameFixer;
use DocbookCS\Fix\Fixer\SimparaFixer;
use DocbookCS\Fix\FixerException;
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
use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Sniff\ExceptionNameSniff;
use DocbookCS\Sniff\Fixable;
use DocbookCS\Sniff\SimparaSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Tests\Support\Fix\InvalidXmlFixer;
use DocbookCS\Tests\Support\Fix\LineBreakFixer;
use DocbookCS\Tests\Support\Fix\ToggleElementFixer;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use DocbookCS\Xml\XmlParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(FixerException::class),
    CoversClass(XmlFileProcessor::class),
    CoversClass(XmlFixRunner::class),
    CoversClass(XmlSniffRunner::class),
    //
    UsesClass(AbstractSniff::class),
    UsesClass(EntityExpansionMarker::class),
    UsesClass(EntityPreprocessor::class),
    UsesClass(ExceptionNameFixer::class),
    UsesClass(ExceptionNameSniff::class),
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
    UsesClass(RunScope::class),
    UsesClass(SimparaFixer::class),
    UsesClass(SimparaSniff::class),
    UsesClass(SourceRange::class),
    UsesClass(Violation::class),
    UsesClass(ViolationScopeFilter::class),
    UsesClass(XmlParser::class),
]
final class FixConvergenceTest extends TestCase
{
    #[Test]
    public function itAppliesIndependentSameLineFixesAndReportsTheFinalSource(): void
    {
        $source = '<root><para>A</para><para><classname>RuntimeException</classname></para></root>';
        $filePath = $this->temporaryFile($source);

        try {
            $processor = new XmlFileProcessor(new XmlSniffRunner(RunMode::Fix, [
                new SimparaSniff(),
                new ExceptionNameSniff(),
            ]));

            $report = $this->processFile($processor, $filePath);

            self::assertSame(
                '<root><simpara>A</simpara><simpara><exceptionname>RuntimeException</exceptionname></simpara></root>',
                file_get_contents($filePath),
            );
            self::assertFalse($report->hasFinalViolations());
            self::assertSame(1, $report->fixingPasses);
        } finally {
            @unlink($filePath);
        }
    }

    #[Test]
    public function itReportsRemainingViolationsAtTheirFinalLines(): void
    {
        $source = '<root><line-break/><bad/></root>';
        $filePath = $this->temporaryFile($source);

        try {
            $lineBreakSniff = new class extends AbstractSniff implements Fixable {
                private const string ELEMENT = '<line-break/>';

                public static function getCode(): string
                {
                    return 'Test.LineBreak';
                }

                public static function getFixerClassName(): string
                {
                    return LineBreakFixer::class;
                }

                public function process(\DOMDocument $document, File $file): array
                {
                    $offset = strpos($file->content, self::ELEMENT);
                    if ($offset === false) {
                        return [];
                    }

                    return [$this->createViolation(
                        $file->path,
                        'Replace the line-break marker.',
                        [new SourceRange(
                            substr_count($file->content, "\n", 0, $offset) + 1,
                            $offset,
                            $offset + strlen(self::ELEMENT),
                            self::ELEMENT,
                        )],
                    )];
                }
            };
            $badElementSniff = new class extends AbstractSniff {
                public static function getCode(): string
                {
                    return 'Test.BadElement';
                }

                public function process(\DOMDocument $document, File $file): array
                {
                    $element = $document->getElementsByTagName('bad')->item(0);
                    if (!$element instanceof \DOMElement) {
                        return [];
                    }

                    $offset = strpos($file->content, '<bad/>');
                    if ($offset === false) {
                        return [];
                    }

                    return [$this->createViolation(
                        $file->path,
                        'Bad element.',
                        [new SourceRange($element->getLineNo(), $offset, $offset + strlen('<bad/>'), '<bad/>')],
                    )];
                }
            };
            $processor = new XmlFileProcessor(new XmlSniffRunner(RunMode::Fix, [
                $lineBreakSniff,
                $badElementSniff,
            ]));

            $report = $this->processFile($processor, $filePath);

            self::assertSame("<root>\n<bad/></root>", file_get_contents($filePath));
            self::assertSame(1, $report->getFinalViolationCount());
            self::assertSame(2, $report->finalViolations[0]->rangeOne()->line);
        } finally {
            @unlink($filePath);
        }
    }

    #[Test]
    public function itKeepsChangedLineScopeAlignedAfterFixes(): void
    {
        $source = "<root>\n<line-break/><bad/>\n</root>";
        $filePath = $this->temporaryFile($source);

        try {
            $lineBreakSniff = new class extends AbstractSniff implements Fixable {
                public static function getCode(): string
                {
                    return 'Test.ScopedLineBreak';
                }

                public static function getFixerClassName(): string
                {
                    return LineBreakFixer::class;
                }

                public function process(\DOMDocument $document, File $file): array
                {
                    $element = '<line-break/>';
                    $offset = strpos($file->content, $element);
                    if ($offset === false) {
                        return [];
                    }

                    return [$this->createViolation(
                        $file->path,
                        'Replace the line-break marker.',
                        [new SourceRange(2, $offset, $offset + strlen($element), $element)],
                    )];
                }
            };
            $badElementSniff = new class extends AbstractSniff {
                public static function getCode(): string
                {
                    return 'Test.ScopedBadElement';
                }

                public function process(\DOMDocument $document, File $file): array
                {
                    $element = $document->getElementsByTagName('bad')->item(0);
                    $offset = strpos($file->content, '<bad/>');

                    if (!$element instanceof \DOMElement || $offset === false) {
                        return [];
                    }

                    return [$this->createViolation(
                        $file->path,
                        'Bad element.',
                        [new SourceRange($element->getLineNo(), $offset, $offset + strlen('<bad/>'), '<bad/>')],
                    )];
                }
            };
            $processor = new XmlFileProcessor(
                new XmlSniffRunner(RunMode::Fix, [$lineBreakSniff, $badElementSniff])
            );

            $report = $this->processFile(
                $processor,
                $filePath,
                new FileChange($filePath, [2]),
            );

            self::assertSame("<root>\n\n<bad/>\n</root>", file_get_contents($filePath));
            self::assertSame(1, $report->getFinalViolationCount());
            self::assertSame(3, $report->finalViolations[0]->rangeOne()->line);
        } finally {
            @unlink($filePath);
        }
    }

    #[Test]
    public function itDoesNotPersistFixesThatCycle(): void
    {
        $source = '<root><alpha/></root>';
        $filePath = $this->temporaryFile($source);

        try {
            $toggleElementSniff = new class extends AbstractSniff implements Fixable {
                public static function getCode(): string
                {
                    return 'Test.ToggleElement';
                }

                public static function getFixerClassName(): string
                {
                    return ToggleElementFixer::class;
                }

                public function process(\DOMDocument $document, File $file): array
                {
                    $element = str_contains($file->content, '<alpha/>') ? '<alpha/>' : '<beta/>';
                    $offset = strpos($file->content, $element);
                    if ($offset === false) {
                        return [];
                    }

                    return [$this->createViolation(
                        $file->path,
                        'Toggle the element.',
                        [new SourceRange(1, $offset, $offset + strlen($element), $element)],
                    )];
                }
            };
            $processor = new XmlFileProcessor(new XmlSniffRunner(RunMode::Fix, [
                $toggleElementSniff,
            ]));

            try {
                $this->processFile($processor, $filePath);
                self::fail('Expected the cycling fixer to fail.');
            } catch (FixerException $exception) {
                self::assertStringContainsString('did not converge', $exception->getMessage());
            }

            self::assertSame($source, file_get_contents($filePath));
        } finally {
            @unlink($filePath);
        }
    }

    #[Test]
    public function itDoesNotPersistFixesThatProduceInvalidXml(): void
    {
        $source = '<root><valid/></root>';
        $filePath = $this->temporaryFile($source);

        try {
            $invalidXmlSniff = new class extends AbstractSniff implements Fixable {
                public static function getCode(): string
                {
                    return 'Test.InvalidXml';
                }

                public static function getFixerClassName(): string
                {
                    return InvalidXmlFixer::class;
                }

                public function process(\DOMDocument $document, File $file): array
                {
                    $offset = strpos($file->content, '<valid/>');
                    if ($offset === false) {
                        return [];
                    }

                    return [$this->createViolation(
                        $file->path,
                        'Produce invalid XML.',
                        [new SourceRange(1, $offset, $offset + strlen('<valid/>'), '<valid/>')],
                    )];
                }
            };
            $processor = new XmlFileProcessor(new XmlSniffRunner(RunMode::Fix, [$invalidXmlSniff]));

            try {
                $this->processFile($processor, $filePath);
                self::fail('Expected the invalid fixer result to fail.');
            } catch (FixerException $exception) {
                self::assertStringContainsString('produced invalid XML', $exception->getMessage());
            }

            self::assertSame($source, file_get_contents($filePath));
        } finally {
            @unlink($filePath);
        }
    }

    private function processFile(XmlFileProcessor $processor, string $path, ?FileChange $fileChange = null): FileReport
    {
        $content = file_get_contents($path);
        self::assertIsString($content);

        $fixedFile = $processor->process(
            $file = new File($path, $content),
            $fileReport = new FileReport($path),
            RunScope::fromFileAndFileChange($file, $fileChange)
        );

        if ($fixedFile !== null) {
            file_put_contents($path, $fixedFile->content);
        }

        return $fileReport;
    }

    private function temporaryFile(string $content): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'docbook-cs-');
        self::assertIsString($filePath);
        file_put_contents($filePath, $content);

        return $filePath;
    }
}
