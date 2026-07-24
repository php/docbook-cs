<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\Fixer\AttributeOrderFixer;
use DocbookCS\Fix\FixResult;
use DocbookCS\Runner\RunMode;
use DocbookCS\Sniff\AttributeOrderSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(AttributeOrderFixer::class),
    CoversClass(AttributeOrderSniff::class),
    CoversClass(Fix::class),
    CoversClass(FixApplier::class),
    CoversClass(FixResult::class),
    CoversClass(RunMode::class),
    CoversClass(Violation::class),
    //
    UsesClass(File::class),
    UsesClass(FixPlan::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
]
final class AttributeOrderFixerTest extends TestCase
{
    #[Test]
    public function itMovesXmlIdBeforeXmlns(): void
    {
        $content = '<root xmlns="urn:test" xml:id="root"/>';
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $violations = new AttributeOrderSniff(RunMode::Fix)->process($document, $source);

        $beginOffset = (int)strpos($content, '<root');
        $sourceContent = '<root xmlns="urn:test" xml:id="root"/>';

        self::assertCount(1, $violations);
        self::assertSame($sourceContent, $violations[0]->content);
        self::assertSame($beginOffset, $violations[0]->beginOffset);
        self::assertSame($beginOffset + strlen($sourceContent), $violations[0]->untilOffset);
        self::assertSame(1, $violations[0]->line);

        $fix = new AttributeOrderFixer()->process($violations[0]);

        $result = new FixApplier()->apply($source, [ $fix ]);

        self::assertSame('<root xml:id="root" xmlns="urn:test"/>', $result->file->content);
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function itPreservesTagShapedTextInsideComments(): void
    {
        $content = '<root><!-- <tag xmlns="urn:test" xml:id="commented"/> -->'
            . '<tag xmlns="urn:test" xml:id="source"/></root>';
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $violations = new AttributeOrderSniff(RunMode::Fix)->process($document, $source);

        self::assertCount(1, $violations);

        $fix = new AttributeOrderFixer()->process($violations[0]);
        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame(
            '<root><!-- <tag xmlns="urn:test" xml:id="commented"/> -->'
                . '<tag xml:id="source" xmlns="urn:test"/></root>',
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
