<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Path;

use DocbookCS\Path\EntityResolver;
use DocbookCS\Runner\EntityPreprocessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityResolver::class)]
#[UsesClass(EntityPreprocessor::class)]
final class EntityResolverTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        $this->fixtureRoot = __DIR__ . '/../../fixtures/entity_tree';

        if (!is_dir($this->fixtureRoot)) {
            self::markTestSkipped('Fixture entity_tree not found.');
        }
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoEntityPathsGiven(): void
    {
        $resolver = new EntityResolver([], []);

        self::assertSame([], $resolver->resolve());
    }

    #[Test]
    public function itSilentlySkipsNonexistentPaths(): void
    {
        $resolver = new EntityResolver([], ['/nonexistent/path/that/does/not/exist']);

        self::assertSame([], $resolver->resolve());
    }

    #[Test]
    public function itParsesSimpleEntityDeclarations(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot . '/global.ent']);
        $entities = $resolver->resolve();

        self::assertSame('Acme Widget', $entities['product']);
        self::assertSame('1.0.0', $entities['version']);
    }

    #[Test]
    public function itNormalizesWhitespaceInValues(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot . '/global.ent']);
        $entities = $resolver->resolve();

        self::assertSame('hello world', $entities['greeting']);
    }

    #[Test]
    public function itHandlesParameterEntities(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot . '/quoted.ent']);
        $entities = $resolver->resolve();

        self::assertArrayHasKey('param_entity', $entities);
        self::assertSame('param-value', $entities['param_entity']);
    }

    #[Test]
    public function itHandlesSingleQuotedValues(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot . '/quoted.ent']);
        $entities = $resolver->resolve();

        self::assertSame('single', $entities['single_quoted']);
    }

    #[Test]
    public function itReturnsEmptyForFileWithoutEntityKeyword(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot . '/plain.ent']);

        self::assertSame([], $resolver->resolve());
    }

    #[Test]
    public function itReturnsEmptyWhenEntityKeywordPresentButNoMatches(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot . '/broken.ent']);

        self::assertSame([], $resolver->resolve());
    }

    #[Test]
    public function itResolvesSystemEntitiesByRelativePath(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot . '/system/main.ent']);
        $entities = $resolver->resolve();

        self::assertArrayHasKey('inc', $entities);
        self::assertArrayHasKey('child', $entities);
        self::assertSame('child-value', $entities['child']);
    }

    #[Test]
    public function itResolvesSystemEntitiesByAbsolutePath(): void
    {
        $included = $this->fixtureRoot . '/system/included.ent';

        $tmpFile = $this->fixtureRoot . '/system/abs_main.ent';
        file_put_contents($tmpFile, '<!ENTITY abs_inc SYSTEM "' . $included . '">');

        try {
            $resolver = new EntityResolver([], [$tmpFile]);
            $entities = $resolver->resolve();

            self::assertArrayHasKey('child', $entities);
            self::assertSame('child-value', $entities['child']);
        } finally {
            @unlink($tmpFile);
        }
    }


    #[Test]
    public function itHandlesCircularSystemReferencesViaVisitedTracking(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot . '/circular/a.ent']);
        $entities = $resolver->resolve();

        self::assertIsArray($entities);
        self::assertArrayHasKey('a_inc', $entities);
    }

    #[Test]
    public function itSkipsUnreadableSystemTargets(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot . '/system/missing_target.ent']);
        $entities = $resolver->resolve();

        self::assertArrayNotHasKey('missing', $entities);
    }

    #[Test]
    public function itResolvesPathsViaProjectRoots(): void
    {
        $realRoot = $this->fixtureRoot . '/project_root/real-root';

        $resolver = new EntityResolver(
            [$realRoot => 'virtual-dir'],
            [$this->fixtureRoot . '/project_root/main.ent']
        );

        $entities = $resolver->resolve();

        self::assertArrayHasKey('mapped', $entities);
        self::assertSame('mapped-value', $entities['mapped']);
    }

    #[Test]
    public function itLeavesReferenceUnchangedWhenProjectRootDirectoryNotMatched(): void
    {
        $resolver = new EntityResolver(
            ['/some/unrelated/root' => 'unrelated-dir'],
            [$this->fixtureRoot . '/system/main.ent']
        );

        $entities = $resolver->resolve();

        self::assertArrayHasKey('child', $entities);
        self::assertSame('child-value', $entities['child']);
    }

    #[Test]
    public function itFindsEntityFilesRecursivelyInDirectory(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot]);
        $entities = $resolver->resolve();

        self::assertArrayHasKey('product', $entities);
        self::assertArrayHasKey('inner', $entities);
        self::assertArrayNotHasKey('ignored', $entities);
    }

    #[Test]
    public function itIgnoresFilesWithWrongExtension(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot . '/not_an_entity.xml']);

        self::assertSame([], $resolver->resolve());
    }

    #[Test]
    public function itSupportsCustomExtension(): void
    {
        $resolver = new EntityResolver(
            [],
            [$this->fixtureRoot . '/custom/custom.dtd'],
            'dtd'
        );

        $entities = $resolver->resolve();

        self::assertSame('custom-value', $entities['custom_ent']);
    }

    #[Test]
    public function itStripsLeadingDotFromExtension(): void
    {
        $resolver = new EntityResolver(
            [],
            [$this->fixtureRoot . '/global.ent'],
            '.ent'
        );

        $entities = $resolver->resolve();

        self::assertSame('Acme Widget', $entities['product']);
    }

    #[Test]
    public function itPreservesFirstDefinitionOnDuplicateEntityNames(): void
    {
        $resolver = new EntityResolver([], [
            $this->fixtureRoot . '/duplicates/a_dup.ent',
            $this->fixtureRoot . '/duplicates/b_dup.ent',
        ]);

        $entities = $resolver->resolve();

        self::assertSame('first', $entities['shared']);
    }

    #[Test]
    public function itHandlesMultilineEntityValues(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot . '/multiline.ent']);
        $entities = $resolver->resolve();

        self::assertSame('line one line two line three', $entities['block']);
    }

    #[Test]
    public function itParsesXmlWrappedEntityFormat(): void
    {
        $entities = new EntityResolver([], [$this->fixtureRoot . '/xml_format.ent'])->resolve();

        self::assertSame('<title>Alphabetical</title>', $entities['extcat.alphabetical']);
        self::assertSame('', $entities['frontpage.authors']);
        self::assertStringContainsString('<title>Extension List/Categorization</title>', $entities['extcat.intro']);
        self::assertStringContainsString('150 extensions', $entities['extcat.intro']);
    }

    #[Test]
    public function itExpandsAliasedXmlEntityThroughPreprocessor(): void
    {
        $entities = new EntityResolver([], [$this->fixtureRoot . '/xml_format.ent'])->resolve();

        $preprocessor = new EntityPreprocessor($entities);

        self::assertSame(
            '<root>renamed value</root>',
            $preprocessor->process('<root>&old.name;</root>')
        );
    }

    #[Test]
    public function itReturnsEmptyWhenXmlEntityKeywordPresentButNoMatches(): void
    {
        $resolver = new EntityResolver([], [$this->fixtureRoot . '/xml_broken.ent']);

        self::assertSame([], $resolver->resolve());
    }
}
