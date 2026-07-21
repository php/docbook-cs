<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Progress;

use DocbookCS\Progress\NullProgress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(NullProgress::class),
]
final class NullProgressTest extends TestCase
{
    #[Test]
    public function itCompletesFullLifecycleWithoutError(): void
    {
        $progress = new NullProgress();

        $progress->start(100);
        $progress->advance(1, 'file.xml', 0);
        $progress->advance(2, 'file2.xml', 5);
        $progress->finish();

        $this->addToAssertionCount(1);
    }
}
