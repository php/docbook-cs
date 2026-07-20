<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\Fixer\ExceptionNameFixer;
use DocbookCS\Fix\FixResult;
use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Runner\RunMode;
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
    CoversClass(ExceptionNameFixer::class),
    CoversClass(ExceptionNameSniff::class),
    CoversClass(Fix::class),
    CoversClass(FixApplier::class),
    CoversClass(FixResult::class),
    CoversClass(RunMode::class),
    CoversClass(Violation::class),
    //
    UsesClass(EntityExpansionMarker::class),
    UsesClass(File::class),
    UsesClass(FixPlan::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
]
final class ExceptionNameFixerTest extends TestCase
{
    #[Test]
    public function itReplacesSimpleClassnameTags(): void
    {
        $content = '<root><classname>RuntimeException</classname></root>';
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $violations = new ExceptionNameSniff(RunMode::Fix)->process($document, $source);

        $beginOffset = (int) strpos($content, '<classname>');
        $sourceContent = '<classname>RuntimeException</classname>';

        self::assertCount(1, $violations);
        self::assertSame($sourceContent, $violations[0]->content);
        self::assertSame($beginOffset, $violations[0]->beginOffset);
        self::assertSame($beginOffset + strlen($sourceContent), $violations[0]->untilOffset);
        self::assertSame(1, $violations[0]->line);

        $fix = new ExceptionNameFixer()->process($violations[0]);

        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame('<root><exceptionname>RuntimeException</exceptionname></root>', $result->file->content);
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function itPreservesClassnameAttributes(): void
    {
        $content = '<root><classname linkend="runtime-exception">RuntimeException</classname></root>';
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $violations = new ExceptionNameSniff(RunMode::Fix)->process($document, $source);

        self::assertCount(1, $violations);
        self::assertSame(
            '<classname linkend="runtime-exception">RuntimeException</classname>',
            $violations[0]->content,
        );

        $fix = new ExceptionNameFixer()->process($violations[0]);

        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame(
            '<root><exceptionname linkend="runtime-exception">RuntimeException</exceptionname></root>',
            $result->file->content,
        );
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function itKeepsSourceContentAlignedAfterRegularClassnames(): void
    {
        $content = '<root><classname>RegularClass</classname><classname>RuntimeException</classname></root>';
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $violations = new ExceptionNameSniff(RunMode::Fix)->process($document, $source);

        $sourceContent = '<classname>RuntimeException</classname>';
        $beginOffset = (int) strpos($content, $sourceContent);

        self::assertCount(1, $violations);
        self::assertSame($sourceContent, $violations[0]->content);
        self::assertSame($beginOffset, $violations[0]->beginOffset);
        self::assertSame($beginOffset + strlen($sourceContent), $violations[0]->untilOffset);

        $fix = new ExceptionNameFixer()->process($violations[0]);

        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame(
            '<root><classname>RegularClass</classname><exceptionname>RuntimeException</exceptionname></root>',
            $result->file->content,
        );
        self::assertSame(1, $result->applied);
    }

    private function createDocument(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        return $document;
    }
}
