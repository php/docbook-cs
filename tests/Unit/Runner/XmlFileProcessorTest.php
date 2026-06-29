<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Report\Severity;
use DocbookCS\Report\Violation;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Sniff\SniffInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityPreprocessor::class)]
#[CoversClass(FileReport::class)]
#[CoversClass(Violation::class)]
#[CoversClass(XmlFileProcessor::class)]
#[CoversClass(Report::class)]
final class XmlFileProcessorTest extends TestCase
{
    #[Test]
    public function itReportsParseErrors(): void
    {
        $report = $this->processor()->processString('<broken><unclosed>', 'bad.xml');

        $this->assertInternalError($report, 'XML parse error');
    }

    #[Test]
    public function itReportsMissingFiles(): void
    {
        $report = $this->processor()->processFile('/nonexistent/path/file.xml');

        $this->assertInternalError($report, 'Could not read file');
    }

    #[Test]
    public function itPrefersReportPathOverFilePath(): void
    {
        $report = $this->processor()->processFile(
            '/nonexistent/path/file.xml',
            [],
            'relative/file.xml'
        );

        self::assertSame('relative/file.xml', $report->filePath);
    }

    #[Test]
    public function itAcceptsValidXmlWithoutViolations(): void
    {
        $xml = $this->xml('<chapter><simpara>ok</simpara></chapter>');

        $report = $this->processor()->processString($xml);

        self::assertFalse($report->hasViolations());
    }

    #[Test]
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

        $report = $processor->processString($xml);

        self::assertCount(
            0,
            array_filter(
                $report->getViolations(),
                fn($v) => $v->sniffCode === 'DocbookCS.Internal'
            )
        );
    }

    #[Test]
    public function itUsesCustomPreprocessor(): void
    {
        $processor = $this->processor([], new EntityPreprocessor([
            'custom.entity' => '[X]',
        ]));

        $xml = $this->xml('<chapter><simpara>&custom.entity;</simpara></chapter>');

        $report = $processor->processString($xml);

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

        $report = $this->processor()->processString($xml);

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

        $report = $this->processor([$sniff])->processString($xml);

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

        $report = $this->processor([$sniff])->processString($xml, 'f.xml', [3]);

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

        $report = $this->processor([$sniff])->processString($xml, 'x.xml', [6]);

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

        $report = $this->processor([$sniff])->processString($xml, 'x.xml', [3]);

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

        $report = $this->processor([$sniff])->processString($xml, 'x.xml', [4]);

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

        $report = $this->processor([$sniff])->processString($xml, 'x.xml', [4]);

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

        $report = $this->processor([$sniff])->processString($xml, 'x.xml', [6]);

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

        $report = $this->processor([$sniff])->processString($xml, 'x.xml', [7]);

        self::assertSame(0, $report->getViolationCount());
    }

    #[Test]
    public function itKeepsInternalErrorsEvenWithDiffFiltering(): void
    {
        $report = $this->processor()->processFile('/nonexistent/path/file.xml', [42]);

        self::assertTrue($report->hasViolations());
        self::assertSame('DocbookCS.Internal', $report->getViolations()[0]->sniffCode);
    }

    /** @param list<int> $lines */
    private function sniff(array $lines): SniffInterface
    {
        return new class ($lines) implements SniffInterface {
            /** @param list<int> $lines */
            public function __construct(private readonly array $lines)
            {
            }

            public function getCode(): string
            {
                return 'Test.Stub';
            }

            public function process(\DOMDocument $document, string $content, string $filePath): array
            {
                return array_map(
                    fn(int $line) => new Violation(
                        sniffCode: $this->getCode(),
                        filePath: $filePath,
                        line: $line,
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
