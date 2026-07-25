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
