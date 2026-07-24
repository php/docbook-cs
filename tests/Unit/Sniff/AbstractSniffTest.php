<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Source\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(AbstractSniff::class),
]
final class AbstractSniffTest extends TestCase
{
    private function createSniff(): AbstractSniff
    {
        return new class extends AbstractSniff {
            public static function getCode(): string
            {
                return 'test.sniff';
            }

            public function process(\DOMDocument $document, File $file): array
            {
                return [];
            }

            public function exposeGet(string $name, string $default = ''): string
            {
                return $this->getProperty($name, $default);
            }
        };
    }

    #[Test]
    public function itStoresAndRetrievesProperties(): void
    {
        $sniff = $this->createSniff();

        $sniff->setProperty('foo', 'bar');

        // @phpstan-ignore-next-line
        self::assertSame('bar', $sniff->exposeGet('foo'));
    }

    #[Test]
    public function itReturnsDefaultWhenPropertyNotSet(): void
    {
        $sniff = $this->createSniff();

        // @phpstan-ignore-next-line
        self::assertSame('default', $sniff->exposeGet('missing', 'default'));
    }
}
