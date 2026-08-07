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
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[Test, DataProvider('knownExceptionNameProvider')]
    public function itRecognisesAllKnownExceptionNames(string $name): void
    {
        self::assertTrue(ExceptionNameSniff::looksLikeException($name));
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
        $untilOffset = (int) strpos($content, '</classname>');

        self::assertCount(1, $violations);
        self::assertSame('classname', $violations[0]->rangeOne()->content);
        self::assertSame('classname', $violations[0]->rangeTwo()->content);
        self::assertEquals([
            new SourceRange(1, $beginOffset + 1, $beginOffset + 10, 'classname'),
            new SourceRange(1, $untilOffset + 2, $untilOffset + 11, 'classname'),
        ], $violations[0]->affectedRanges);
    }

    #[Test]
    public function itKeepsSourceContentAlignedAfterRegularClassnames(): void
    {
        $content = '<root><classname>RegularClass</classname><classname>RuntimeException</classname></root>';
        $doc = $this->createDocument($content);

        $violations = new ExceptionNameSniff()->process($doc, new File('file.xml', $content));

        $sourceContent = '<classname>RuntimeException</classname>';
        $beginOffset = (int) strpos($content, $sourceContent);
        $untilOffset = (int) strpos($content, '</classname>', $beginOffset);

        self::assertCount(1, $violations);
        self::assertSame('classname', $violations[0]->rangeOne()->content);
        self::assertSame('classname', $violations[0]->rangeTwo()->content);
        self::assertEquals([
            new SourceRange(1, $beginOffset + 1, $beginOffset + 10, 'classname'),
            new SourceRange(1, $untilOffset + 2, $untilOffset + 11, 'classname'),
        ], $violations[0]->affectedRanges);
    }

    #[Test]
    public function itIgnoresClassnamesInsideCommentsWhenMappingSource(): void
    {
        $content = '<root><!-- <classname>CommentedException</classname> -->'
            . '<classname>RuntimeException</classname></root>';
        $doc = $this->createDocument($content);

        $violations = new ExceptionNameSniff()->process($doc, new File('file.xml', $content));

        self::assertCount(1, $violations);
        self::assertSame('classname', $violations[0]->rangeOne()->content);
        self::assertSame('classname', $violations[0]->rangeTwo()->content);
    }

    #[Test]
    public function itRejectsSourceThatDoesNotMatchTheParsedDocument(): void
    {
        $document = $this->createDocument('<root><classname>RuntimeException</classname></root>');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIs('Could not map classname violation to source content.');

        new ExceptionNameSniff()->process(
            $document,
            new File('file.xml', '<root><classname>OtherException</classname></root>'),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function knownExceptionNameProvider(): iterable
    {
        $names = [
            'ArgumentCountError',
            'ArithmeticError',
            'AssertionError',
            'BadFunctionCallException',
            'BadMethodCallException',
            'ClosedGeneratorException',
            'CompileError',
            'com_exception',
            'DateError',
            'DateException',
            'DateInvalidOperationException',
            'DateInvalidTimeZoneException',
            'DateMalformedIntervalStringException',
            'DateMalformedPeriodStringException',
            'DateMalformedStringException',
            'DateObjectError',
            'DateRangeError',
            'DivisionByZeroError',
            'DomainException',
            'DOMException',
            'Dom\\DOMException',
            'Error',
            'ErrorException',
            'Exception',
            'FFI\\Exception',
            'FFI\\ParserException',
            'FiberError',
            'Filter\\FilterException',
            'Filter\\FilterFailedException',
            'IntlException',
            'InvalidArgumentException',
            'JsonException',
            'LengthException',
            'LogicException',
            'mysqli_sql_exception',
            'OutOfBoundsException',
            'OutOfRangeException',
            'OverflowException',
            'ParseError',
            'PDOException',
            'PharException',
            'parallel\\Channel\\Error\\Closed',
            'parallel\\Channel\\Error\\Existence',
            'parallel\\Channel\\Error\\IllegalValue',
            'parallel\\Events\\Error\\Existence',
            'parallel\\Events\\Error\\Timeout',
            'parallel\\Events\\Input\\Error\\Existence',
            'parallel\\Events\\Input\\Error\\IllegalValue',
            'parallel\\Future\\Error\\Cancelled',
            'parallel\\Future\\Error\\Foreign',
            'parallel\\Future\\Error\\Killed',
            'parallel\\Runtime\\Error\\Bootstrap',
            'parallel\\Runtime\\Error\\Closed',
            'parallel\\Runtime\\Error\\IllegalFunction',
            'parallel\\Runtime\\Error\\IllegalInstruction',
            'parallel\\Runtime\\Error\\IllegalParameter',
            'parallel\\Runtime\\Error\\IllegalReturn',
            'parallel\\Runtime\\Error\\IllegalVariable',
            'parallel\\Runtime\\Error\\Killed',
            'parallel\\Sync\\Error\\IllegalValue',
            'Random\\BrokenRandomEngineError',
            'Random\\RandomError',
            'Random\\RandomException',
            'RangeException',
            'ReflectionException',
            'RequestParseBodyException',
            'RuntimeException',
            'SNMPException',
            'SoapFault',
            'SodiumException',
            'SQLite3Exception',
            'svmexception',
            'Swoole\\Exception\\ArrayKeyNotExists',
            'TypeError',
            'UnderflowException',
            'UnexpectedValueException',
            'UnhandledMatchError',
            'Uri\\InvalidUriException',
            'Uri\\UriError',
            'Uri\\UriException',
            'Uri\\WhatWg\\InvalidUrlException',
            'ValueError',
            'Yaf\\Exception\\DispatchFailed',
            'Yaf\\Exception\\LoadFailed',
            'Yaf\\Exception\\LoadFailed\\Action',
            'Yaf\\Exception\\LoadFailed\\Controller',
            'Yaf\\Exception\\LoadFailed\\Module',
            'Yaf\\Exception\\LoadFailed\\View',
            'Yaf\\Exception\\RouterFailed',
        ];

        foreach ($names as $name) {
            yield $name => [$name];
        }
    }
}
