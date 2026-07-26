<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Tests\Support\Sniff\ExposedAbstractSniff;
use DocbookCS\Violation\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(AbstractSniff::class),
]
final class AbstractSniffTest extends TestCase
{
    #[Test]
    public function itStoresAndRetrievesProperties(): void
    {
        $sniff = new ExposedAbstractSniff();
        $sniff->setProperty('foo', 'bar');

        self::assertSame('bar', $sniff->exposeGet('foo'));
    }

    #[Test]
    public function itReturnsDefaultWhenPropertyNotSet(): void
    {
        self::assertSame('default', new ExposedAbstractSniff()->exposeGet('missing', 'default'));
    }

    #[Test]
    public function itUsesErrorAsTheDefaultSeverity(): void
    {
        self::assertSame(Severity::ERROR, new ExposedAbstractSniff()->exposeSeverity());
    }

    #[Test]
    public function itOverridesSeverityFromProperties(): void
    {
        $sniff = new ExposedAbstractSniff();
        $sniff->setProperty('severity', 'warning');

        self::assertSame(Severity::WARNING, $sniff->exposeSeverity());
    }

    #[Test]
    public function itRejectsInvalidSeverityProperties(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExposedAbstractSniff()->setProperty('severity', 'invalid');
    }
}
