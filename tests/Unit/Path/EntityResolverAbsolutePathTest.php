<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Path;

use DocbookCS\Path\EntityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * SYSTEM references written by doc-base's configure.php are absolute,
 * which on Windows means they start with a drive letter (C:/...).
 */
#[
    CoversClass(EntityResolver::class),
]
final class EntityResolverAbsolutePathTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'docbook-cs-entity-absolute-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/doc-base', recursive: true);
        file_put_contents($this->directory . '/doc-base/included.ent', '<!ENTITY child "child-value">');
    }

    protected function tearDown(): void
    {
        @unlink($this->directory . '/doc-base/included.ent');
        @unlink($this->directory . '/doc-base/main.ent');
        @rmdir($this->directory . '/doc-base');
        @rmdir($this->directory);
    }

    #[Test]
    public function itResolvesAbsoluteSystemReferences(): void
    {
        $included = str_replace('\\', '/', $this->directory) . '/doc-base/included.ent';
        $main = $this->writeMain($included);

        $resolver = new EntityResolver([], [$main]);

        self::assertSame($included, $resolver->paths()['inc']);
        self::assertSame('child-value', $resolver->resolve()['child']);
    }

    #[Test]
    public function itResolvesAbsoluteSystemReferencesRewrittenToAProjectRoot(): void
    {
        // An absolute path from another checkout, mapped onto the local doc-base.
        $main = $this->writeMain('/elsewhere/doc-base/included.ent');

        $resolver = new EntityResolver([$this->directory . '/doc-base' => 'doc-base'], [$main]);

        self::assertSame($this->directory . '/doc-base/included.ent', $resolver->paths()['inc']);
        self::assertSame('child-value', $resolver->resolve()['child']);
    }

    private function writeMain(string $reference): string
    {
        $main = $this->directory . '/doc-base/main.ent';
        file_put_contents($main, '<!ENTITY inc SYSTEM "' . $reference . '">');

        return $main;
    }
}
