<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\Fixer\NativeTypeFixer;
use DocbookCS\Fix\FixResult;
use DocbookCS\Runner\RunMode;
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
    CoversClass(NativeTypeFixer::class),
    CoversClass(NativeTypeSniff::class),
    UsesClass(File::class),
    UsesClass(Fix::class),
    UsesClass(FixApplier::class),
    UsesClass(FixResult::class),
    UsesClass(Line::class),
    UsesClass(RunMode::class),
    UsesClass(SourceRange::class),
    UsesClass(Violation::class),
]
final class NativeTypeFixerTest extends TestCase
{
    #[Test]
    public function itCanonicalizesNativeTypeNames(): void
    {
        $content = '<methodsynopsis><type>Array</type><methodname>example</methodname>'
            . '<methodparam><type>integer</type><parameter>value</parameter></methodparam></methodsynopsis>';

        $result = $this->fix($content);

        self::assertSame(
            '<methodsynopsis><type>array</type><methodname>example</methodname>'
                . '<methodparam><type>int</type><parameter>value</parameter></methodparam></methodsynopsis>',
            $result->file->content,
        );
        self::assertSame(2, $result->applied);
    }

    #[Test]
    public function itCollapsesAUnionContainingMixed(): void
    {
        $content = '<methodsynopsis><type class="union"><type>mixed</type><type>null</type></type>'
            . '<methodname>example</methodname></methodsynopsis>';

        $result = $this->fix($content);

        self::assertSame(
            '<methodsynopsis><type>mixed</type><methodname>example</methodname></methodsynopsis>',
            $result->file->content,
        );
        self::assertSame(1, $result->applied);
    }

    private function fix(string $content): FixResult
    {
        $file = new File('file.xml', $content);
        $document = new \DOMDocument();
        $document->loadXML($content);
        $violations = new NativeTypeSniff(RunMode::Fix)->process($document, $file);
        $fixer = new NativeTypeFixer();
        $fixes = array_map($fixer->process(...), $violations);

        return new FixApplier()->apply($file, $fixes);
    }
}
