<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Report\Violation;
use DocbookCS\Sniff\WhitespaceSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(Violation::class),
    CoversClass(WhitespaceSniff::class),
]
final class WhitespaceSniffTest extends TestCase
{
    private function createDocument(string $xml): \DOMDocument
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        return $doc;
    }

    #[Test]
    public function itReturnsEmptyWhenNoViolations(): void
    {
        $content = "<root>" . PHP_EOL .
            "    <tag>value</tag>" . PHP_EOL .
            "</root>";

        $doc = $this->createDocument($content);
        $violations = (new WhitespaceSniff())->process($doc, $content, 'file.xml');

        self::assertSame([], $violations);
    }

    #[Test]
    public function itDetectsTrailingWhitespace(): void
    {
        $content = "<root> " . PHP_EOL .
            "</root>";

        $doc = $this->createDocument($content);
        $violations = (new WhitespaceSniff())->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertSame('Trailing whitespace detected.', $violations[0]->message);
        self::assertSame(1, $violations[0]->line);
    }

    #[Test]
    public function itDetectsSpaceBeforeTab(): void
    {
        $content = "<root>" . PHP_EOL .
            " \t<tag/>" . PHP_EOL .
            "</root>";

        $doc = $this->createDocument($content);
        $violations = (new WhitespaceSniff())->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertSame('Mixed tabs and spaces in indentation.', $violations[0]->message);
        self::assertSame(2, $violations[0]->line);
    }

    #[Test]
    public function itDetectsMixedIndentationSpacesThenTabs(): void
    {
        $content = "<root>" . PHP_EOL .
            "  \t<tag/>" . PHP_EOL .
            "</root>";

        $doc = $this->createDocument($content);
        $violations = (new WhitespaceSniff())->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertSame('Mixed tabs and spaces in indentation.', $violations[0]->message);
    }

    #[Test]
    public function itDetectsMixedIndentationTabsThenSpaces(): void
    {
        $content = "<root>" . PHP_EOL .
            "\t  \t<tag/>" . PHP_EOL .
            "</root>";

        $doc = $this->createDocument($content);
        $violations = (new WhitespaceSniff())->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertSame('Mixed tabs and spaces in indentation.', $violations[0]->message);
    }

    #[Test]
    public function itHandlesMultipleViolations(): void
    {
        $content = "<root> " . PHP_EOL .
            " \t<tag/>" . PHP_EOL .
            "\t  \t<tag/>" . PHP_EOL .
            "</root>";

        $doc = $this->createDocument($content);
        $violations = (new WhitespaceSniff())->process($doc, $content, 'file.xml');

        self::assertCount(3, $violations);
    }

    #[Test]
    public function itIncludesFilePathInViolation(): void
    {
        $content = "<root> " . PHP_EOL .
            "</root>";

        $doc = $this->createDocument($content);
        $violations = (new WhitespaceSniff())->process($doc, $content, 'my-file.xml');

        self::assertCount(1, $violations);
        self::assertSame('my-file.xml', $violations[0]->filePath);
    }
}
