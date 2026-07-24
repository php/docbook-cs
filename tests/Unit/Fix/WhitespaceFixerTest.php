<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\Fixer\WhitespaceFixer;
use DocbookCS\Fix\FixResult;
use DocbookCS\Runner\RunMode;
use DocbookCS\Sniff\WhitespaceSniff;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(Fix::class),
    CoversClass(FixApplier::class),
    CoversClass(FixResult::class),
    CoversClass(RunMode::class),
    CoversClass(Violation::class),
    CoversClass(WhitespaceFixer::class),
    CoversClass(WhitespaceSniff::class),
    //
    UsesClass(File::class),
    UsesClass(FixPlan::class),
    UsesClass(SourceRange::class),
]
final class WhitespaceFixerTest extends TestCase
{
    #[Test]
    public function itFixesOnlySniffedLines(): void
    {
        $content = "<root> \n \t<tag/>\n</root>";
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $violations = new WhitespaceSniff(RunMode::Fix)->process($document, $source);

        $secondLineOffset = (int) strpos($content, " \t<tag/>");

        self::assertCount(2, $violations);
        self::assertSame('<root> ', $violations[0]->content);
        self::assertSame(0, $violations[0]->beginOffset);
        self::assertSame(strlen('<root> '), $violations[0]->untilOffset);
        self::assertSame(" \t<tag/>", $violations[1]->content);
        self::assertSame($secondLineOffset, $violations[1]->beginOffset);
        self::assertSame($secondLineOffset + strlen(" \t<tag/>"), $violations[1]->untilOffset);

        $fixes = [];
        $fixer = new WhitespaceFixer();
        foreach ($violations as $violation) {
            $fixes[] = $fixer->process($violation);
        }

        $result = new FixApplier()->apply($source, $fixes);

        self::assertSame("<root>\n  <tag/>\n</root>", $result->file->content);
        self::assertSame(2, $result->applied);
    }

    private function createDocument(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        return $document;
    }
}
