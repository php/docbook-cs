<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Fix\FixerException;
use DocbookCS\Report\FileReport;
use DocbookCS\Runner\XmlProcessingResult;
use DocbookCS\Source\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(File::class),
    CoversClass(FileReport::class),
    CoversClass(FixerException::class),
    CoversClass(XmlProcessingResult::class),
]
final class XmlProcessingResultTest extends TestCase
{
    #[Test]
    public function itHasNoPendingFixesForIdenticalContent(): void
    {
        $result = new XmlProcessingResult(
            new FileReport('input.xml'),
            new File('input.xml', '<root/>'),
            new File('input.xml', '<root/>'),
        );

        self::assertFalse($result->isModified());
    }

    #[Test]
    public function itHasPendingFixesForModifiedContent(): void
    {
        $result = new XmlProcessingResult(
            new FileReport('input.xml'),
            new File('input.xml', '<root/>'),
            new File('input.xml', '<root fixed="fixed"/>'),
        );

        self::assertTrue($result->isModified());
    }

    #[Test]
    public function itThrowsWhenReadingFixedContentWithoutFixApplication(): void
    {
        $result = new XmlProcessingResult(
            new FileReport('input.xml'),
            new File('input.xml', '<root/>'),
            new File('input.xml', '<root/>'),
        );

        $this->expectException(FixerException::class);
        $this->expectExceptionMessageIsOrContains('Cannot read fixed content when no fix application was attempted.');

        $result->fixedContent();
    }

    #[Test]
    public function itReturnsFixedContentWhenFixApplicationExists(): void
    {
        $result = new XmlProcessingResult(
            new FileReport('input.xml'),
            new File('input.xml', '<root/>'),
            new File('input.xml', '<root fixed="fixed"/>'),
        );

        self::assertSame('<root fixed="fixed"/>', $result->fixedContent());
    }
}
