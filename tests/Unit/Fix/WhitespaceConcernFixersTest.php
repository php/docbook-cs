<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\Fixer\FileEmptyLastLineFixer;
use DocbookCS\Fix\Fixer\MixedIndentationFixer;
use DocbookCS\Fix\Fixer\TrailingWhitespaceFixer;
use DocbookCS\Fix\FixResult;
use DocbookCS\Sniff\FileEmptyLastLineSniffer;
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
    CoversClass(FileEmptyLastLineFixer::class),
    CoversClass(FileEmptyLastLineSniffer::class),
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

        $trailingSniffer = new TrailingWhitespaceSniff();
        $indentationSniffer = new MixedIndentationSniff();

        $trailingViolations = $trailingSniffer->process($document, $source);
        $indentationViolations = $indentationSniffer->process($document, $source);

        self::assertCount(2, $trailingViolations);
        self::assertCount(1, $indentationViolations);

        $fixes = [];
        $trailingFixer = new ($trailingSniffer::getFixerClassName())();
        foreach ($trailingViolations as $violation) {
            $fixes[] = $trailingFixer->process($violation);
        }

        $indentationFixer = new ($indentationSniffer::getFixerClassName())();
        foreach ($indentationViolations as $violation) {
            $fixes[] = $indentationFixer->process($violation);
        }

        $result = new FixApplier()->apply($source, $fixes);

        self::assertSame("<root>\n  <tag/>\n</root>", $result->file->content);
        self::assertSame(3, $result->applied);
        self::assertSame(0, $result->skipped);
    }

    #[Test]
    public function itFixesTrailingWhitespaceAndTheFileEndingTogether(): void
    {
        $content = "<root/> \n\n";
        $document = new \DOMDocument();
        $document->loadXML($content);
        $source = new File('file.xml', $content);

        $trailingViolation = new TrailingWhitespaceSniff()->process($document, $source)[0];
        $fileEndingViolation = new FileEmptyLastLineSniffer()->process($document, $source)[0];

        $result = new FixApplier()->apply($source, [
            new TrailingWhitespaceFixer()->process($trailingViolation),
            new FileEmptyLastLineFixer()->process($fileEndingViolation),
        ]);

        self::assertSame("<root/>\n", $result->file->content);
        self::assertSame(2, $result->applied);
        self::assertSame(0, $result->skipped);
    }
}
