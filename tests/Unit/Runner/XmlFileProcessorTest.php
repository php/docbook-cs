<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Diff\FileChange;
use DocbookCS\Fix\Fixer\AttributeOrderFixer;
use DocbookCS\Fix\FixerException;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\SourceScope;
use DocbookCS\Runner\ViolationScopeFilter;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Runner\XmlProcessingResult;
use DocbookCS\Sniff\Fixable;
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(EntityPreprocessor::class),
    CoversClass(FileReport::class),
    CoversClass(Report::class),
    CoversClass(Violation::class),
    CoversClass(ViolationScopeFilter::class),
    CoversClass(XmlFileProcessor::class),
    //
    UsesClass(AttributeOrderFixer::class),
    UsesClass(File::class),
    UsesClass(FileChange::class),
    UsesClass(FixerException::class),
    UsesClass(Line::class),
    UsesClass(RunMode::class),
    UsesClass(SourceRange::class),
    UsesClass(SourceScope::class),
    UsesClass(XmlProcessingResult::class),
]
final class XmlFileProcessorTest extends TestCase
{
    #[Test]
    public function itReportsParseErrors(): void
    {
        $report = $this->process($this->processor(), '<broken><unclosed>', 'bad.xml');

        $this->assertInternalError($report, 'XML parse error');
    }

    #[Test]
    public function itReportsParseErrorsOutsideChangedSourceRanges(): void
    {
        $report = $this->process(
            $this->processor(),
            '<broken><unclosed>',
            'bad.xml',
            new FileChange('bad.xml', [99]),
        );

        $this->assertInternalError($report, 'XML parse error');
    }

    #[Test]
    public function itStoresTheProvidedFilePathInFileReports(): void
    {
        $filePath = (getcwd() ?: '') . '/nonexistent/path/file.xml';
        $report = $this->process($this->processor(), '<root/>', $filePath);

        self::assertSame($filePath, $report->filePath);
    }

    #[Test]
    public function itAcceptsValidXmlWithoutViolations(): void
    {
        $xml = $this->xml('<chapter><simpara>ok</simpara></chapter>');

        $report = $this->process($this->processor(), $xml);

        self::assertFalse($report->hasViolations());
    }

    #[Test] // TODO: should be integration
    public function itHandlesEntitiesWithoutParseErrors(): void
    {
        $xml = $this->xml(
            '<!DOCTYPE chapter SYSTEM "docbook.dtd">
        <chapter>
          <simpara>&link.superglobals; &php.ini; &amp;</simpara>
        </chapter>'
        );

        $processor = $this->processor([], new EntityPreprocessor([
            'link.superglobals' => '',
            'php.ini' => '',
        ]));

        $report = $this->process($processor, $xml);

        self::assertCount(
            0,
            array_filter(
                $report->getViolations(),
                fn($v) => $v->sniffCode === 'DocbookCS.Internal'
            )
        );
    }

    #[Test] // TODO: should be integration
    public function itUsesCustomPreprocessor(): void
    {
        $processor = $this->processor([], new EntityPreprocessor([
            'custom.entity' => '[X]',
        ]));

        $xml = $this->xml('<chapter><simpara>&custom.entity;</simpara></chapter>');

        $report = $this->process($processor, $xml);

        self::assertCount(
            0,
            array_filter(
                $report->getViolations(),
                fn($v) => $v->sniffCode === 'DocbookCS.Internal'
            )
        );
    }

    #[Test]
    public function itReturnsZeroViolationsWithoutSniffs(): void
    {
        $xml = $this->xml('<chapter><para>Hello</para></chapter>');

        $report = $this->process($this->processor(), $xml);

        self::assertSame(0, $report->getViolationCount());
    }

    #[Test]
    public function itReturnsAllViolationsWithoutDiffFiltering(): void
    {
        $sniff = $this->sniff([3, 5]);

        $xml = $this->xml(
            '<chapter>
          <simpara>3</simpara>
          <simpara>4</simpara>
          <simpara>5</simpara>
        </chapter>'
        );

        $report = $this->process($this->processor([$sniff]), $xml);

        self::assertSame(2, $report->getViolationCount());
    }

    #[Test]
    public function itFiltersViolationsByChangedLines(): void
    {
        $sniff = $this->sniff([3, 5]);

        $xml = $this->xml(
            '<chapter>
          <simpara>3</simpara>
          <simpara>4</simpara>
          <simpara>5</simpara>
        </chapter>'
        );

        $report = $this->process(
            $this->processor([$sniff]),
            $xml,
            'f.xml',
            new FileChange('f.xml', [3]),
        );

        self::assertSame(1, $report->getViolationCount());
        self::assertSame(3, $report->getViolations()[0]->line);
    }

    #[Test]
    public function itExpandsElementSpanForNestedChanges(): void
    {
        $sniff = $this->sniff([3]);

        $xml = $this->xml(
            '<chapter>
          <para>
            <emphasis>
              <literal>line 6</literal>
            </emphasis>
          </para>
        </chapter>'
        );

        $report = $this->process(
            $this->processor([$sniff]),
            $xml,
            'x.xml',
            new FileChange('x.xml', [6]),
        );

        self::assertSame(1, $report->getViolationCount());
    }

    #[Test]
    public function itDropsViolationsWhoseLineHasNoElement(): void
    {
        $sniff = $this->sniff([999]);

        $xml = $this->xml(
            '<chapter>
          <para>hello</para>
        </chapter>'
        );

        $report = $this->process(
            $this->processor([$sniff]),
            $xml,
            'x.xml',
            new FileChange('x.xml', [3]),
        );

        self::assertSame(0, $report->getViolationCount());
    }

    #[Test]
    public function itMatchesChangesInElementOwnTextContent(): void
    {
        $sniff = $this->sniff([3]);

        $xml = $this->xml(
            '<chapter>
      <simpara>
        hello
      </simpara>
    </chapter>'
        );

        $report = $this->process(
            $this->processor([$sniff]),
            $xml,
            'x.xml',
            new FileChange('x.xml', [4]),
        );

        self::assertSame(1, $report->getViolationCount());
    }

    #[Test]
    public function itBoundsChildSpanByNextSibling(): void
    {
        $sniff = $this->sniff([3]);

        $xml = $this->xml(
            '<chapter>
      <para>
        <emphasis>X</emphasis>
        <link>Y</link>
      </para>
    </chapter>'
        );

        $report = $this->process(
            $this->processor([$sniff]),
            $xml,
            'x.xml',
            new FileChange('x.xml', [4]),
        );

        self::assertSame(1, $report->getViolationCount());
    }

    #[Test]
    public function itIgnoresChangesInNonDirectDescendants(): void
    {
        $sniff = $this->sniff([3]);

        $xml = $this->xml(
            '<chapter>
          <refentry>
            <refsect1>
              <methodsynopsis>
                <type>array</type>
              </methodsynopsis>
            </refsect1>
          </refentry>
        </chapter>'
        );

        $report = $this->process(
            $this->processor([$sniff]),
            $xml,
            'x.xml',
            new FileChange('x.xml', [6]),
        );

        self::assertSame(0, $report->getViolationCount());
    }

    #[Test]
    public function itIgnoresChangesOutsideElementSpan(): void
    {
        $sniff = $this->sniff([3]);

        $xml = $this->xml(
            '<chapter>
          <para>text</para>
          <simpara>line 7</simpara>
        </chapter>'
        );

        $report = $this->process(
            $this->processor([$sniff]),
            $xml,
            'x.xml',
            new FileChange('x.xml', [7]),
        );

        self::assertSame(0, $report->getViolationCount());
    }

    #[Test]
    public function itReportsNoViolationsInDiffModeWhenNoLinesWereAdded(): void
    {
        $sniff = $this->sniff([3, 5]);

        $xml = $this->xml(
            '<chapter>
          <simpara>3</simpara>
          <simpara>4</simpara>
          <simpara>5</simpara>
        </chapter>'
        );

        $report = $this->process(
            $this->processor([$sniff]),
            $xml,
            'f.xml',
            new FileChange('f.xml', []),
        );

        self::assertSame(0, $report->getViolationCount());
    }

    #[Test]
    public function itDoesNotFixViolationsFromNonFixableSniffs(): void
    {
        $sniff = new class (RunMode::Sniff) implements SniffInterface {
            public function __construct(public RunMode $mode)
            {
            }

            public static function getCode(): string
            {
                return 'Test.NonFixable';
            }

            public function process(\DOMDocument $document, File $file): array
            {
                return [
                    new Violation(
                        sniffCode: self::getCode(),
                        filePath: $file->path,
                        line: 2,
                        beginOffset: 0,
                        untilOffset: 7,
                        message: 'Reported only.',
                        content: '<root/>',
                        severity: Severity::ERROR,
                    ),
                ];
            }

            public function setProperty(string $name, string $value): void
            {
            }
        };

        $report = $this->process($this->processor([$sniff]), $this->xml('<root/>'));

        self::assertSame(1, $report->getViolationCount());
    }

    #[Test]
    public function itThrowsWhenFixableSniffReportsViolationWithoutContentInFixMode(): void
    {
        $sniff = new class (RunMode::Fix) implements Fixable {
            public function __construct(public RunMode $mode)
            {
            }

            public static function getCode(): string
            {
                return 'Test.BrokenFixable';
            }

            public static function fixerClassName(): string
            {
                return AttributeOrderFixer::class;
            }

            public function process(\DOMDocument $document, File $file): array
            {
                return [
                    new Violation(
                        sniffCode: self::getCode(),
                        filePath: $file->path,
                        line: 1,
                        beginOffset: 0,
                        untilOffset: 7,
                        message: 'Missing source content.',
                        severity: Severity::ERROR,
                    ),
                ];
            }

            public function setProperty(string $name, string $value): void
            {
            }
        };

        $this->expectException(FixerException::class);
        $this->expectExceptionMessageIsOrContains('Violations cannot be content-less when passed to a fixer.');

        $this->process($this->processor([$sniff]), $this->xml('<root xmlns="urn:test" xml:id="root"/>'));
    }

    /** @param list<int> $lines */
    private function sniff(array $lines): SniffInterface
    {
        $sniff = new class (RunMode::Sniff) implements SniffInterface {
            /** @var list<int> */
            public array $lines = [];

            public function __construct(public RunMode $mode)
            {
            }

            public static function getCode(): string
            {
                return 'Test.Stub';
            }

            public function process(\DOMDocument $document, File $file): array
            {
                return array_map(
                    fn(int $line) => new Violation(
                        sniffCode: self::getCode(),
                        filePath: $file->path,
                        line: $line,
                        beginOffset: 0,
                        untilOffset: 0,
                        message: "violation at line {$line}",
                        severity: Severity::WARNING
                    ),
                    $this->lines
                );
            }

            public function setProperty(string $name, string $value): void
            {
            }
        };

        $sniff->lines = $lines;

        return $sniff;
    }

    private function process(
        XmlFileProcessor $processor,
        string $content,
        string $path = 'input.xml',
        ?FileChange $fileChange = null,
    ): FileReport {
        return $processor->process(new File($path, $content), $fileChange)->fileReport;
    }

    /** @param list<SniffInterface> $sniffs */
    private function processor(array $sniffs = [], ?EntityPreprocessor $pre = null): XmlFileProcessor
    {
        return new XmlFileProcessor(
            $sniffs,
            $pre ?? new EntityPreprocessor([]) // always pass array
        );
    }

    private function xml(string $body): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
$body
XML;
    }

    private function assertInternalError(FileReport $report, string $messagePart): void
    {
        self::assertTrue($report->hasViolations());
        self::assertSame('DocbookCS.Internal', $report->getViolations()[0]->sniffCode);
        self::assertStringContainsString($messagePart, $report->getViolations()[0]->message);
    }
}
