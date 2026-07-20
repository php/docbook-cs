<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Diff\FileChange;
use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixResult;
use DocbookCS\Fix\Fixer\AttributeOrderFixer;
use DocbookCS\Fix\Fixer\ExceptionNameFixer;
use DocbookCS\Fix\Fixer\SimparaFixer;
use DocbookCS\Fix\FixerException;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\SourceScope;
use DocbookCS\Runner\ViolationScopeFilter;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Runner\XmlProcessingResult;
use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Sniff\ExceptionNameSniff;
use DocbookCS\Sniff\Fixable;
use DocbookCS\Sniff\SimparaSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Tests\Support\Fix\LineBreakFixer;
use DocbookCS\Tests\Support\Fix\ToggleElementFixer;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(XmlFileProcessor::class),
    //
    UsesClass(AbstractSniff::class),
    UsesClass(AttributeOrderFixer::class),
    UsesClass(EntityExpansionMarker::class),
    UsesClass(EntityPreprocessor::class),
    UsesClass(ExceptionNameFixer::class),
    UsesClass(ExceptionNameSniff::class),
    UsesClass(File::class),
    UsesClass(FileChange::class),
    UsesClass(FileReport::class),
    UsesClass(Fix::class),
    UsesClass(FixApplier::class),
    UsesClass(FixerException::class),
    UsesClass(FixPlan::class),
    UsesClass(FixResult::class),
    UsesClass(Line::class),
    UsesClass(Report::class),
    UsesClass(RunMode::class),
    UsesClass(SimparaFixer::class),
    UsesClass(SimparaSniff::class),
    UsesClass(SourceRange::class),
    UsesClass(SourceScope::class),
    UsesClass(Violation::class),
    UsesClass(ViolationScopeFilter::class),
    UsesClass(XmlProcessingResult::class),
]
final class FixConvergenceTest extends TestCase
{
    #[Test]
    public function itAppliesIndependentSameLineFixesAndReportsTheFinalSource(): void
    {
        $source = '<root><para>A</para><para><classname>RuntimeException</classname></para></root>';
        $filePath = $this->temporaryFile($source);

        try {
            $processor = new XmlFileProcessor([
                new SimparaSniff(RunMode::Fix),
                new ExceptionNameSniff(RunMode::Fix),
            ]);

            $report = $this->processFile($processor, $filePath);

            self::assertSame(
                '<root><simpara>A</simpara><simpara><exceptionname>RuntimeException</exceptionname></simpara></root>',
                file_get_contents($filePath),
            );
            self::assertFalse($report->hasViolations());
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
            $lineBreakSniff = new class (RunMode::Fix) extends AbstractSniff implements Fixable {
                private const string ELEMENT = '<line-break/>';

                public static function getCode(): string
                {
                    return 'Test.LineBreak';
                }

                public static function fixerClassName(): string
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
                        substr_count($file->content, "\n", 0, $offset) + 1,
                        $offset,
                        $offset + strlen(self::ELEMENT),
                        'Replace the line-break marker.',
                        self::ELEMENT,
                    )];
                }
            };
            $badElementSniff = new class (RunMode::Fix) extends AbstractSniff {
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
                        $element->getLineNo(),
                        $offset,
                        $offset + strlen('<bad/>'),
                        'Bad element.',
                        '<bad/>',
                    )];
                }
            };
            $processor = new XmlFileProcessor([
                $lineBreakSniff,
                $badElementSniff,
            ]);

            $report = $this->processFile($processor, $filePath);

            self::assertSame("<root>\n<bad/></root>", file_get_contents($filePath));
            self::assertSame(1, $report->getViolationCount());
            self::assertSame(2, $report->getViolations()[0]->line);
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
            $lineBreakSniff = new class (RunMode::Fix) extends AbstractSniff implements Fixable {
                public static function getCode(): string
                {
                    return 'Test.ScopedLineBreak';
                }

                public static function fixerClassName(): string
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
                        2,
                        $offset,
                        $offset + strlen($element),
                        'Replace the line-break marker.',
                        $element,
                    )];
                }
            };
            $badElementSniff = new class (RunMode::Fix) extends AbstractSniff {
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
                        $element->getLineNo(),
                        $offset,
                        $offset + strlen('<bad/>'),
                        'Bad element.',
                        '<bad/>',
                    )];
                }
            };
            $processor = new XmlFileProcessor([$lineBreakSniff, $badElementSniff]);

            $report = $this->processFile(
                $processor,
                $filePath,
                new FileChange($filePath, [2]),
            );

            self::assertSame("<root>\n\n<bad/>\n</root>", file_get_contents($filePath));
            self::assertSame(1, $report->getViolationCount());
            self::assertSame(3, $report->getViolations()[0]->line);
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
            $toggleElementSniff = new class (RunMode::Fix) extends AbstractSniff implements Fixable {
                public static function getCode(): string
                {
                    return 'Test.ToggleElement';
                }

                public static function fixerClassName(): string
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
                        1,
                        $offset,
                        $offset + strlen($element),
                        'Toggle the element.',
                        $element,
                    )];
                }
            };
            $processor = new XmlFileProcessor([
                $toggleElementSniff,
            ]);

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
            $invalidXmlSniff = new class (RunMode::Fix) extends AbstractSniff implements Fixable {
                public static function getCode(): string
                {
                    return 'Test.InvalidXml';
                }

                public static function fixerClassName(): string
                {
                    return AttributeOrderFixer::class;
                }

                public function process(\DOMDocument $document, File $file): array
                {
                    $offset = strpos($file->content, '<valid/>');
                    if ($offset === false) {
                        return [];
                    }

                    return [$this->createViolation(
                        $file->path,
                        1,
                        $offset,
                        $offset + strlen('<valid/>'),
                        'Produce invalid XML.',
                        '<tag xmlns="urn:test" xml:id="id">',
                    )];
                }
            };
            $processor = new XmlFileProcessor([$invalidXmlSniff]);

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

        $result = $processor->process(new File($path, $content), $fileChange);
        if ($result->isModified()) {
            file_put_contents($path, $result->fixedContent());
        }

        return $result->fileReport;
    }

    private function temporaryFile(string $content): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'docbook-cs-');
        self::assertIsString($filePath);
        file_put_contents($filePath, $content);

        return $filePath;
    }
}
