<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Sniff\ExceptionNameSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(ExceptionNameSniff::class),
    CoversClass(Violation::class),
    //
    UsesClass(EntityExpansionMarker::class),
    UsesClass(File::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
]
final class ExceptionNameSniffTest extends TestCase
{
    private function createDocument(string $xml): \DOMDocument
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        return $doc;
    }

    #[Test]
    public function itReturnsEmptyWhenNoClassnameNodes(): void
    {
        $content = '<root></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, new File('test.xml', $content));

        self::assertSame([], $violations);
    }

    #[Test]
    public function itIgnoresEmptyClassname(): void
    {
        $content = '<root><classname>   </classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, new File('test.xml', $content));

        self::assertSame([], $violations);
    }

    #[Test]
    public function itDoesNotFlagRegularClassnames(): void
    {
        $content = '<root><classname>MyService</classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, new File('test.xml', $content));

        self::assertSame([], $violations);
    }

    #[Test]
    public function itFlagsExceptionSuffix(): void
    {
        $content = '<root><classname>RuntimeException</classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, new File('file.xml', $content));

        self::assertCount(1, $violations);
        self::assertStringContainsString(
            'RuntimeException',
            $violations[0]->message
        );
    }

    #[Test]
    public function itFlagsErrorSuffix(): void
    {
        $content = '<root><classname>TypeError</classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, new File('file.xml', $content));

        self::assertCount(1, $violations);
        self::assertStringContainsString(
            'TypeError',
            $violations[0]->message
        );
    }

    #[Test]
    public function itFlagsThrowableSuffix(): void
    {
        $content = '<root><classname>CustomThrowable</classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, new File('file.xml', $content));

        self::assertCount(1, $violations);
        self::assertStringContainsString(
            'CustomThrowable',
            $violations[0]->message
        );
    }

    #[Test]
    public function itHandlesNamespacedClassnames(): void
    {
        $content = '<root><classname>Foo\Bar\BazException</classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, new File('file.xml', $content));

        self::assertCount(1, $violations);
        self::assertStringContainsString(
            'BazException',
            $violations[0]->message
        );
    }

    #[Test]
    public function itOnlyChecksBaseNameInNamespace(): void
    {
        $content = '<root><classname>Exception\ButNotActually</classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, new File('file.xml', $content));

        self::assertSame([], $violations);
    }

    #[Test]
    public function itHandlesMultipleClassnames(): void
    {
        $content = '<root>
                <classname>ValidClass</classname>
                <classname>LogicException</classname>
                <classname>AnotherClass</classname>
                <classname>FatalError</classname>
            </root>';

        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, new File('file.xml', $content));

        self::assertCount(2, $violations);
    }

    #[Test]
    public function itDoesNotFlagClassnameInsideOoclass(): void
    {
        $content = '<root><ooclass><classname>RuntimeException</classname></ooclass></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, new File('file.xml', $content));

        self::assertSame([], $violations);
    }

    #[Test]
    public function itIncludesFilePathInViolation(): void
    {
        $content = '<root><classname>RuntimeException</classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, new File('my-file.xml', $content));

        self::assertCount(1, $violations);
        self::assertSame('my-file.xml', $violations[0]->filePath);
    }

    #[Test]
    public function itAddsSourceContent(): void
    {
        $content = '<root><classname>RuntimeException</classname></root>';
        $doc = $this->createDocument($content);

        $violations = new ExceptionNameSniff()->process($doc, new File('file.xml', $content));

        $beginOffset = (int) strpos($content, '<classname>');
        $sourceContent = '<classname>RuntimeException</classname>';

        self::assertCount(1, $violations);
        self::assertSame($sourceContent, $violations[0]->content);
        self::assertSame($beginOffset, $violations[0]->beginOffset);
        self::assertSame($beginOffset + strlen($sourceContent), $violations[0]->untilOffset);
        self::assertSame(1, $violations[0]->line);
    }

    #[Test]
    public function itKeepsSourceContentAlignedAfterRegularClassnames(): void
    {
        $content = '<root><classname>RegularClass</classname><classname>RuntimeException</classname></root>';
        $doc = $this->createDocument($content);

        $violations = new ExceptionNameSniff()->process($doc, new File('file.xml', $content));

        $sourceContent = '<classname>RuntimeException</classname>';
        $beginOffset = (int) strpos($content, $sourceContent);

        self::assertCount(1, $violations);
        self::assertSame($sourceContent, $violations[0]->content);
        self::assertSame($beginOffset, $violations[0]->beginOffset);
        self::assertSame($beginOffset + strlen($sourceContent), $violations[0]->untilOffset);
    }
}
