<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Diff\FileChange;
use DocbookCS\Fix\Fixer\AttributeOrderFixer;
use DocbookCS\Fix\FixerException;
use DocbookCS\Report\FileReport;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Runner\XmlFixRunner;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunScope;
use DocbookCS\Runner\ViolationScopeFilter;
use DocbookCS\Runner\XmlSniffRunner;
use DocbookCS\Sniff\Fixable;
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use DocbookCS\Xml\XmlParser;
use DocbookCS\Tests\Support\XmlHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(EntityPreprocessor::class),
    CoversClass(FileReport::class),
    CoversClass(Violation::class),
    CoversClass(ViolationScopeFilter::class),
    CoversClass(XmlSniffRunner::class),
    //
    UsesClass(AttributeOrderFixer::class),
    UsesClass(File::class),
    UsesClass(FileChange::class),
    UsesClass(FixerException::class),
    UsesClass(Line::class),
    UsesClass(RunMode::class),
    UsesClass(RunScope::class),
    UsesClass(SourceRange::class),
    UsesClass(XmlFileProcessor::class),
    UsesClass(XmlFixRunner::class),
    UsesClass(XmlParser::class),
]
final class XmlSniffRunnerTest extends TestCase
{
    use XmlHelper;

    #[Test]
    public function itReportsParseErrors(): void
    {
        $report = $this->process($this->runner(), '<broken><unclosed>', 'bad.xml');

        $this->assertInternalError($report, 'XML parse error');
    }

    #[Test]
    public function itReportsParseErrorsOutsideChangedSourceRanges(): void
    {
        $report = $this->process(
            $this->runner(),
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
        $report = $this->process($this->runner(), '<root/>', $filePath);

        self::assertSame($filePath, $report->filePath);
    }

    #[Test]
    public function itAcceptsValidXmlWithoutViolations(): void
    {
        $xml = $this->xml('<chapter><simpara>ok</simpara></chapter>');

        $report = $this->process($this->runner(), $xml);

        self::assertFalse($report->hasFinalViolations());
    }

    #[Test]
    public function itReturnsZeroViolationsWithoutSniffs(): void
    {
        $xml = $this->xml('<chapter><para>Hello</para></chapter>');

        $report = $this->process($this->runner(), $xml);

        self::assertSame(0, $report->getFinalViolationCount());
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

        $report = $this->process($this->runner([$sniff]), $xml);

        self::assertSame(2, $report->getFinalViolationCount());
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
            $this->runner([$sniff], mode: RunMode::Fix),
            $xml,
            'f.xml',
            new FileChange('f.xml', [3]),
        );

        self::assertSame(1, $report->getFinalViolationCount());
        self::assertSame(3, $report->finalViolations[0]->rangeOne()->line);
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
            $this->runner([$sniff]),
            $xml,
            'x.xml',
            new FileChange('x.xml', [6]),
        );

        self::assertSame(1, $report->getFinalViolationCount());
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
            $this->runner([$sniff]),
            $xml,
            'x.xml',
            new FileChange('x.xml', [3]),
        );

        self::assertSame(0, $report->getFinalViolationCount());
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
            $this->runner([$sniff]),
            $xml,
            'x.xml',
            new FileChange('x.xml', [4]),
        );

        self::assertSame(1, $report->getFinalViolationCount());
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
            $this->runner([$sniff]),
            $xml,
            'x.xml',
            new FileChange('x.xml', [4]),
        );

        self::assertSame(1, $report->getFinalViolationCount());
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
            $this->runner([$sniff]),
            $xml,
            'x.xml',
            new FileChange('x.xml', [6]),
        );

        self::assertSame(0, $report->getFinalViolationCount());
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
            $this->runner([$sniff]),
            $xml,
            'x.xml',
            new FileChange('x.xml', [7]),
        );

        self::assertSame(0, $report->getFinalViolationCount());
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
            $this->runner([$sniff]),
            $xml,
            'f.xml',
            new FileChange('f.xml', []),
        );

        self::assertSame(0, $report->getFinalViolationCount());
    }

    #[Test]
    public function itDoesNotFixViolationsFromNonFixableSniffs(): void
    {
        $sniff = new class implements SniffInterface {
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
                        message: 'Reported only.',
                        affectedRanges: [new SourceRange(2, 0, 7, '<root/>')],
                        severity: Severity::ERROR,
                    ),
                ];
            }

            public function setProperty(string $name, string $value): void
            {
            }
        };

        $report = $this->process(
            $this->runner([$sniff]),
            $this->xml('<root/>'),
        );

        self::assertSame(1, $report->getFinalViolationCount());
    }

    #[Test]
    public function itThrowsWhenFixableSniffReportsViolationWithoutContentInFixMode(): void
    {
        $sniff = new class implements Fixable {
            public static function getCode(): string
            {
                return 'Test.BrokenFixable';
            }

            public static function getFixerClassName(): string
            {
                return AttributeOrderFixer::class;
            }

            public function process(\DOMDocument $document, File $file): array
            {
                return [
                    new Violation(
                        sniffCode: self::getCode(),
                        filePath: $file->path,
                        message: 'Missing source content.',
                        affectedRanges: [new SourceRange(1, 0, 7)],
                        severity: Severity::ERROR,
                    ),
                ];
            }

            public function setProperty(string $name, string $value): void
            {
            }
        };

        $this->expectException(FixerException::class);
        $this->expectExceptionMessageIsOrContains('Fixers require affected source ranges with source content.');

        $content = $this->xml('<root xmlns="urn:test" xml:id="root"/>');
        $file = new File('input.xml', $content);
        $fileReport = new FileReport($file->path);
        new XmlFileProcessor($this->runner([$sniff], mode: RunMode::Fix))->process(
            $file,
            $fileReport,
            RunScope::fromFileAndFileChange($file, null),
        );
    }

    /** @param list<int> $lines */
    private function sniff(array $lines): SniffInterface
    {
        $sniff = new class implements SniffInterface {
            /** @var list<int> */
            public array $lines = [];

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
                        message: "violation at line {$line}",
                        affectedRanges: [new SourceRange($line, 0, 0)],
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
        XmlSniffRunner $runner,
        string $content,
        string $path = 'input.xml',
        ?FileChange $fileChange = null,
    ): FileReport {
        $file = new File($path, $content);
        $fileReport = new FileReport($path);

        new XmlFileProcessor($runner)->process(
            $file,
            $fileReport,
            RunScope::fromFileAndFileChange($file, $fileChange),
        );

        return $fileReport;
    }

    /** @param list<SniffInterface> $sniffs */
    private function runner(
        array $sniffs = [],
        RunMode $mode = RunMode::Sniff,
    ): XmlSniffRunner {
        return new XmlSniffRunner($mode, $sniffs);
    }

    private function assertInternalError(FileReport $report, string $messagePart): void
    {
        self::assertTrue($report->hasFinalViolations());
        self::assertSame('DocbookCS.Internal', $report->finalViolations[0]->sniffCode);
        self::assertStringContainsString($messagePart, $report->finalViolations[0]->message);
    }
}
