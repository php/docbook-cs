<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Path;

use DocbookCS\Path\EntityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(EntityResolver::class),
]
final class EntityResolverPathsTest extends TestCase
{
    #[Test]
    public function itResolvesEntityContentAndTargetPathsFromTheSameResolution(): void
    {
        $fixtureRoot = __DIR__ . '/../../fixtures/entity_tree/system';
        $resolver = new EntityResolver([], [$fixtureRoot . '/main.ent']);

        $paths = $resolver->paths();
        $entities = $resolver->resolve();

        self::assertSame($fixtureRoot . '/included.ent', $paths['inc']);
        self::assertSame('child-value', $entities['child']);
    }

    #[Test]
    public function itDoesNotExposeUnreadableEntityTargets(): void
    {
        $fixture = __DIR__ . '/../../fixtures/entity_tree/system/missing_target.ent';
        $resolver = new EntityResolver([], [$fixture]);

        self::assertArrayNotHasKey('missing', $resolver->paths());
    }
}
