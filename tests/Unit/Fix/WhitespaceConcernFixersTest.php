<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\Fixer\MixedIndentationFixer;
use DocbookCS\Fix\Fixer\TrailingWhitespaceFixer;
use DocbookCS\Fix\FixResult;
use DocbookCS\Runner\RunMode;
use DocbookCS\Sniff\MixedIndentationSniff;
use DocbookCS\Sniff\TrailingWhitespaceSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
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
    CoversClass(MixedIndentationFixer::class),
    CoversClass(MixedIndentationSniff::class),
    CoversClass(TrailingWhitespaceFixer::class),
    CoversClass(TrailingWhitespaceSniff::class),
    //
    UsesClass(File::class),
    UsesClass(FixPlan::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
    UsesClass(Violation::class),
]
final class WhitespaceConcernFixersTest extends TestCase
{
    #[Test]
    public function itFixesIndependentWhitespaceConcernsTogether(): void
    {
        $content = "<root> \n \t<tag/>  \n</root>";
        $document = new \DOMDocument();
        $document->loadXML($content);
        $source = new File('file.xml', $content);

        $trailingViolations = new TrailingWhitespaceSniff(RunMode::Fix)->process($document, $source);
        $indentationViolations = new MixedIndentationSniff(RunMode::Fix)->process($document, $source);

        self::assertCount(2, $trailingViolations);
        self::assertCount(1, $indentationViolations);

        $fixes = [];
        $trailingFixer = new TrailingWhitespaceFixer();
        foreach ($trailingViolations as $violation) {
            $fixes[] = $trailingFixer->process($violation);
        }

        $indentationFixer = new MixedIndentationFixer();
        foreach ($indentationViolations as $violation) {
            $fixes[] = $indentationFixer->process($violation);
        }

        $result = new FixApplier()->apply($source, $fixes);

        self::assertSame("<root>\n  <tag/>\n</root>", $result->file->content);
        self::assertSame(3, $result->applied);
        self::assertSame(0, $result->skipped);
    }
}
