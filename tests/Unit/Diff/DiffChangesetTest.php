<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Diff;

use DocbookCS\Diff\DiffChangeset;
use DocbookCS\Diff\FileChange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(DiffChangeset::class),
    //
    UsesClass(FileChange::class),
]
final class DiffChangesetTest extends TestCase
{
    #[Test]
    public function itMatchesRelativeDiffPathsAgainstAbsoluteSourcePaths(): void
    {
        $change = new FileChange('reference/file.xml', [2]);
        $changeset = new DiffChangeset([$change]);

        self::assertSame($change, $changeset->changeFor('/project/reference/file.xml'));
    }

    #[Test]
    public function itReturnsNullForUnchangedPaths(): void
    {
        $changeset = new DiffChangeset([new FileChange('reference/file.xml', [2])]);

        self::assertNull($changeset->changeFor('/project/reference/other.xml'));
    }
}
