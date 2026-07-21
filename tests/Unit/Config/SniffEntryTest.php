<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Config;

use DocbookCS\Config\SniffEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(SniffEntry::class),
]
final class SniffEntryTest extends TestCase
{
    #[Test]
    public function itThrowsExceptionForEmptyClassName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Sniff class name must not be empty.');

        new SniffEntry('');
    }
}
