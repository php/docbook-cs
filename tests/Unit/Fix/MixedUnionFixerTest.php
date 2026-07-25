<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\Fixer\MixedUnionFixer;
use DocbookCS\Fix\FixResult;
use DocbookCS\Runner\RunMode;
use DocbookCS\Sniff\MixedUnionDetector;
use DocbookCS\Sniff\MixedUnionSniff;
use DocbookCS\Sniff\NativeTypeSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(MixedUnionFixer::class),
    CoversClass(MixedUnionSniff::class),
    UsesClass(File::class),
    UsesClass(Fix::class),
    UsesClass(FixApplier::class),
    UsesClass(FixResult::class),
    UsesClass(Line::class),
    UsesClass(RunMode::class),
    UsesClass(SourceRange::class),
    UsesClass(Violation::class),
    UsesClass(MixedUnionDetector::class),
    UsesClass(NativeTypeSniff::class),
]
final class MixedUnionFixerTest extends TestCase
{
    #[Test]
    public function itCollapsesAUnionContainingMixed(): void
    {
        $content = '<methodsynopsis><type class="union"><type>mixed</type><type>Array</type></type>'
            . '<methodname>example</methodname></methodsynopsis>';

        $file = new File('file.xml', $content);
        $document = new \DOMDocument();
        $document->loadXML($content);
        $violations = [
            ...new NativeTypeSniff(RunMode::Fix)->process($document, $file),
            ...new MixedUnionSniff(RunMode::Fix)->process($document, $file),
        ];
        self::assertCount(1, $violations);
        self::assertSame('DocbookCS.MixedUnion', $violations[0]->sniffCode);
        $fixer = new MixedUnionFixer();
        $result = new FixApplier()->apply($file, array_map($fixer->process(...), $violations));

        self::assertSame(
            '<methodsynopsis><type>mixed</type><methodname>example</methodname></methodsynopsis>',
            $result->file->content,
        );
        self::assertSame(1, $result->applied);
    }
}
