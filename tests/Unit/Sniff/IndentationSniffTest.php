<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\IndentationAnalyzer;
use DocbookCS\Sniff\IndentationSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(IndentationSniff::class),
    //
    UsesClass(File::class),
    UsesClass(IndentationAnalyzer::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
    UsesClass(Violation::class),
]
final class IndentationSniffTest extends TestCase
{
    #[Test]
    public function itReportsStructuralAndMixedIndentationFromIssue47(): void
    {
        $violations = $this->process($this->fixture('issue_47.xml'));

        self::assertCount(9, $violations);
        self::assertContains("\t   ", array_map(
            static fn(Violation $violation): ?string => $violation->rangeOne()->content,
            $violations,
        ));
        self::assertSame('DocbookCS.Indentation', $violations[0]->sniffCode);
        self::assertMatchesRegularExpression('/^Expected indentation of \d+ spaces?\.$/', $violations[0]->message);
        self::assertIsInt($violations[0]->fixerData);
    }

    #[Test]
    public function itAcceptsCanonicalOneSpaceIndentation(): void
    {
        self::assertSame([], $this->process($this->fixture('issue_47.fixed.xml')));
    }

    #[Test]
    public function itLeavesDocBookAndXmlPreservedWhitespaceAlone(): void
    {
        self::assertSame([], $this->process($this->fixture('preserved.xml')));
    }

    #[Test]
    public function itReportsTabsEvenWhenTheyAreNotMixedWithSpaces(): void
    {
        $violations = $this->process("<root>\n\t<child />\n</root>");

        self::assertCount(1, $violations);
        self::assertSame("\t", $violations[0]->rangeOne()->content);
        self::assertSame('Expected indentation of 1 space.', $violations[0]->message);
        self::assertSame(1, $violations[0]->fixerData);
    }

    /** @return list<Violation<int>> */
    private function process(string $content): array
    {
        $document = new \DOMDocument();
        $document->loadXML($content);

        return new IndentationSniff()->process($document, new File('file.xml', $content));
    }

    private function fixture(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/indentation/' . $name);
        self::assertIsString($content);

        return $content;
    }
}
