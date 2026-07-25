<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Runner\RunScope;
use DocbookCS\Runner\ViolationScopeFilter;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(ViolationScopeFilter::class),
    //
    UsesClass(File::class),
    UsesClass(SourceRange::class),
    UsesClass(RunScope::class),
    UsesClass(Violation::class),
]
final class ViolationScopeFilterTest extends TestCase
{
    #[Test]
    public function itReturnsAllViolationsForWholeFileScope(): void
    {
        $file = new File('file.xml', '<root/>');
        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($file->content));
        $violations = [new Violation(
            sniffCode: 'Test.Stub',
            filePath: $file->path,
            message: 'violation at line 1',
            affectedRanges: [new SourceRange(1, 0, 0)],
        )];

        $result = new ViolationScopeFilter()->filter(
            $violations,
            $document,
            $file,
            RunScope::fromFileAndFileChange($file, null),
        );

        self::assertSame($violations, $result);
    }
}
