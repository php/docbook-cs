<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixResult;
use DocbookCS\Source\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(Fix::class),
    CoversClass(FixApplier::class),
    CoversClass(FixPlan::class),
    CoversClass(FixResult::class),
    //
    UsesClass(File::class),
]
final class FixPlanTest extends TestCase
{
    #[Test]
    public function itLeavesTheSourceUntouchedWithoutFixes(): void
    {
        $source = new File('file.xml', '<root/>');

        $result = new FixApplier()->apply($source, []);

        self::assertSame($source, $result->file);
        self::assertSame(0, $result->applied);
        self::assertSame(0, $result->skipped);
    }

    #[Test]
    public function itSkipsFixesThatAlreadyHaveTheDesiredContent(): void
    {
        $source = new File('file.xml', '<root/>');
        $fix = new Fix('file.xml', 1, 5, 'root', 'Sniff', 'root');

        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame($source->content, $result->file->content);
        self::assertSame(0, $result->applied);
        self::assertSame(1, $result->skipped);
    }

    #[Test]
    public function itSkipsCompetingInsertionsAtTheSameOffset(): void
    {
        $source = new File('file.xml', 'abc');
        $first = new Fix('file.xml', 1, 1, 'X', 'FirstSniff');
        $second = new Fix('file.xml', 1, 1, 'X', 'SecondSniff');

        $result = new FixApplier()->apply($source, [$first, $second]);

        self::assertSame('aXbc', $result->file->content);
        self::assertSame(1, $result->applied);
        self::assertSame(1, $result->skipped);
    }

    #[Test]
    public function itAllowsAnInsertionAtTheStartOfAReplacement(): void
    {
        $source = new File('file.xml', 'abc');
        $insertion = new Fix('file.xml', 1, 1, 'X', 'InsertionSniff');
        $replacement = new Fix('file.xml', 1, 2, 'B', 'ReplacementSniff', 'b');

        $result = new FixApplier()->apply($source, [$insertion, $replacement]);

        self::assertSame('aXBc', $result->file->content);
        self::assertSame(2, $result->applied);
        self::assertSame(0, $result->skipped);
    }

    #[Test]
    public function itSkipsAnInsertionInsideAReplacement(): void
    {
        $source = new File('file.xml', 'abc');
        $replacement = new Fix('file.xml', 0, 3, 'ABC', 'ReplacementSniff', 'abc');
        $insertion = new Fix('file.xml', 1, 1, 'X', 'InsertionSniff');

        $result = new FixApplier()->apply($source, [$replacement, $insertion]);

        self::assertSame('ABC', $result->file->content);
        self::assertSame(1, $result->applied);
        self::assertSame(1, $result->skipped);
    }

    #[Test]
    public function itAppliesEveryFixInAPlanAtomically(): void
    {
        $content = '<para>x</para>';
        $source = new File('file.xml', $content);
        $plan = new FixPlan(
            new Fix('file.xml', 1, 5, 'simpara', 'Sniff', 'para'),
            new Fix('file.xml', 9, 13, 'simpara', 'Sniff', 'para'),
        );

        $result = new FixApplier()->apply($source, [$plan]);

        self::assertSame('<simpara>x</simpara>', $result->file->content);
        self::assertSame(1, $result->applied);
        self::assertSame(0, $result->skipped);
    }

    #[Test]
    public function itAllowsAnIndependentFixBetweenAPlanRanges(): void
    {
        $content = '<para>x</para>';
        $source = new File('file.xml', $content);
        $plan = new FixPlan(
            new Fix('file.xml', 1, 5, 'simpara', 'ElementSniff', 'para'),
            new Fix('file.xml', 9, 13, 'simpara', 'ElementSniff', 'para'),
        );
        $textFix = new Fix('file.xml', 6, 7, 'y', 'TextSniff', 'x');

        $result = new FixApplier()->apply($source, [$plan, $textFix]);

        self::assertSame('<simpara>y</simpara>', $result->file->content);
        self::assertSame(2, $result->applied);
        self::assertSame(0, $result->skipped);
    }

    #[Test]
    public function itSkipsAWholePlanWhenOneRangeIsStale(): void
    {
        $content = '<para>x</parb>';
        $source = new File('file.xml', $content);
        $plan = new FixPlan(
            new Fix('file.xml', 1, 5, 'simpara', 'Sniff', 'para'),
            new Fix('file.xml', 9, 13, 'simpara', 'Sniff', 'para'),
        );

        $result = new FixApplier()->apply($source, [$plan]);

        self::assertSame($content, $result->file->content);
        self::assertSame(0, $result->applied);
        self::assertSame(1, $result->skipped);
    }

    #[Test]
    public function itSkipsAWholePlanWhenOneRangeConflicts(): void
    {
        $content = '<para>x</para>';
        $source = new File('file.xml', $content);
        $openingTagFix = new Fix('file.xml', 1, 5, 'other', 'FirstSniff', 'para');
        $plan = new FixPlan(
            new Fix('file.xml', 1, 5, 'simpara', 'SecondSniff', 'para'),
            new Fix('file.xml', 9, 13, 'simpara', 'SecondSniff', 'para'),
        );

        $result = new FixApplier()->apply($source, [$openingTagFix, $plan]);

        self::assertSame('<other>x</para>', $result->file->content);
        self::assertSame(1, $result->applied);
        self::assertSame(1, $result->skipped);
    }

    #[Test]
    public function itSkipsFixesForAnotherSource(): void
    {
        $content = '<para>x</para>';
        $source = new File('file.xml', $content);
        $fix = new Fix('other.xml', 1, 5, 'simpara', 'Sniff', 'para');

        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame($content, $result->file->content);
        self::assertSame(0, $result->applied);
        self::assertSame(1, $result->skipped);
    }
}
