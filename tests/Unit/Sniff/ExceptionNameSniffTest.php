<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Report\Violation;
use DocbookCS\Sniff\ExceptionNameSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExceptionNameSniff::class)]
#[CoversClass(Violation::class)]
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
        $violations = new ExceptionNameSniff()->process($doc, $content, 'test.xml');

        self::assertSame([], $violations);
    }

    #[Test]
    public function itIgnoresEmptyClassname(): void
    {
        $content = '<root><classname>   </classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, $content, 'test.xml');

        self::assertSame([], $violations);
    }

    #[Test]
    public function itDoesNotFlagRegularClassnames(): void
    {
        $content = '<root><classname>MyService</classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, $content, 'test.xml');

        self::assertSame([], $violations);
    }

    #[Test]
    public function itFlagsExceptionSuffix(): void
    {
        $content = '<root><classname>RuntimeException</classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, $content, 'file.xml');

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
        $violations = new ExceptionNameSniff()->process($doc, $content, 'file.xml');

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
        $violations = new ExceptionNameSniff()->process($doc, $content, 'file.xml');

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
        $violations = new ExceptionNameSniff()->process($doc, $content, 'file.xml');

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
        $violations = new ExceptionNameSniff()->process($doc, $content, 'file.xml');

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
        $violations = new ExceptionNameSniff()->process($doc, $content, 'file.xml');

        self::assertCount(2, $violations);
    }

    #[Test]
    public function itDoesNotFlagClassnameInsideOoclass(): void
    {
        $content = '<root><ooclass><classname>RuntimeException</classname></ooclass></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, $content, 'file.xml');

        self::assertSame([], $violations);
    }

    #[Test]
    public function itIncludesFilePathInViolation(): void
    {
        $content = '<root><classname>RuntimeException</classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, $content, 'my-file.xml');

        self::assertCount(1, $violations);
        self::assertSame('my-file.xml', $violations[0]->filePath);
    }

    #[Test]
    public function itFlagsAdditionalSuffix(): void
    {
        $content = '<root><classname>SoapFault</classname></root>';
        $doc = $this->createDocument($content);

        $sniff = new ExceptionNameSniff();
        $sniff->setProperty('additionalSuffixes', 'Fault');
        $violations = $sniff->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertStringContainsString('SoapFault', $violations[0]->message);
    }

    #[Test]
    public function itDoesNotFlagAdditionalSuffixWhenNotConfigured(): void
    {
        $content = '<root><classname>SoapFault</classname></root>';
        $doc = $this->createDocument($content);
        $violations = new ExceptionNameSniff()->process($doc, $content, 'file.xml');

        self::assertSame([], $violations);
    }

    #[Test]
    public function itParsesMultipleAdditionalSuffixesAndTrimsWhitespace(): void
    {
        $content = '<root>
                <classname>SoapFault</classname>
                <classname>HttpProblem</classname>
            </root>';
        $doc = $this->createDocument($content);

        $sniff = new ExceptionNameSniff();
        $sniff->setProperty('additionalSuffixes', ' Fault , Problem ');
        $violations = $sniff->process($doc, $content, 'file.xml');

        self::assertCount(2, $violations);
    }

    #[Test]
    public function itFlagsAdditionalExactName(): void
    {
        $content = '<root><classname>Whoops</classname></root>';
        $doc = $this->createDocument($content);

        $sniff = new ExceptionNameSniff();
        $sniff->setProperty('additionalExactNames', 'Whoops');
        $violations = $sniff->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertStringContainsString('Whoops', $violations[0]->message);
    }

    #[Test]
    public function itMatchesAdditionalExactNameOnBaseNameInNamespace(): void
    {
        $content = '<root><classname>Foo\Bar\Whoops</classname></root>';
        $doc = $this->createDocument($content);

        $sniff = new ExceptionNameSniff();
        $sniff->setProperty('additionalExactNames', 'Whoops');
        $violations = $sniff->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
    }

    #[Test]
    public function itDoesNotFlagUnrelatedNameWhenOptionsConfigured(): void
    {
        $content = '<root><classname>MyService</classname></root>';
        $doc = $this->createDocument($content);

        $sniff = new ExceptionNameSniff();
        $sniff->setProperty('additionalSuffixes', 'Fault');
        $sniff->setProperty('additionalExactNames', 'Whoops');
        $violations = $sniff->process($doc, $content, 'file.xml');

        self::assertSame([], $violations);
    }
}
