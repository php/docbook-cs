<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Config;

use DocbookCS\Config\ConfigData;
use DocbookCS\Config\ConfigParser;
use DocbookCS\Config\ConfigParserException;
use DocbookCS\Config\SniffEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigParser::class)]
#[CoversClass(ConfigData::class)]
#[CoversClass(SniffEntry::class)]
#[CoversClass(ConfigParserException::class)]
final class ConfigParserTest extends TestCase
{
    private ConfigParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ConfigParser();
    }

    #[Test]
    public function itParsesAFullConfigFromString(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<docbookcs>
    <project>
        <directory alias="doc">en</directory>
    </project>
    <sniffs>
        <sniff class="App\Sniffs\ParaSniff" />
        <sniff class="App\Sniffs\IdSniff">
            <property name="pattern" value="^[a-z]+$" />
        </sniff>
    </sniffs>
    <paths>
        <path>reference/</path>
        <path>language/types.xml</path>
    </paths>
    <exclude>
        <pattern>*/skeleton.xml</pattern>
    </exclude>
    <entities>
        <directory>../doc-base/entities/</directory>
        <file>../doc-base/entities/global.ent</file>
    </entities>
</docbookcs>
XML;

        $basePath = '/project/doc';
        $config = $this->parser->parseString($xml, $basePath);

        // Project roots
        $projectRoots = $config->getProjectRoots();
        self::assertNotEmpty($projectRoots);
        self::assertSame('en', $projectRoots['/project/en']);
        self::assertSame('en', $projectRoots['/project/doc']);

        // Sniffs
        self::assertCount(2, $config->getSniffs());
        self::assertSame('App\Sniffs\ParaSniff', $config->getSniffs()[0]->getClassName());
        self::assertSame([], $config->getSniffs()[0]->getProperties());

        self::assertSame('App\Sniffs\IdSniff', $config->getSniffs()[1]->getClassName());
        self::assertSame('^[a-z]+$', $config->getSniffs()[1]->getProperty('pattern'));

        // Include paths (resolved relative to basePath)
        self::assertCount(2, $config->getIncludePaths());
        self::assertSame('/project/doc/reference', $config->getIncludePaths()[0]);
        self::assertSame('/project/doc/language/types.xml', $config->getIncludePaths()[1]);

        // Exclude patterns (kept as-is)
        self::assertSame(['*/skeleton.xml'], $config->getExcludePatterns());

        // Entity paths (resolved, note the ".." traversal)
        self::assertCount(2, $config->getEntityPaths());
        self::assertSame('/project/doc-base/entities', $config->getEntityPaths()[0]);
        self::assertSame('/project/doc-base/entities/global.ent', $config->getEntityPaths()[1]);

        // Base path
        self::assertSame('/project/doc', $config->getBasePath());
    }

    #[Test]
    public function itParsesMinimalConfig(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<docbookcs>
    <project>
        <directory>en</directory>
    </project>
    <sniffs>
        <sniff class="App\Sniffs\ParaSniff" />
    </sniffs>
</docbookcs>
XML;

        $config = $this->parser->parseString($xml, '/base');

        self::assertCount(1, $config->getSniffs());
        self::assertSame([], $config->getIncludePaths());
        self::assertSame([], $config->getExcludePatterns());
        self::assertSame([], $config->getEntityPaths());
    }

    #[Test]
    public function itResolvesAbsolutePathsAsIs(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<docbookcs>
    <project>
        <directory>en</directory>
    </project>
    <sniffs>
        <sniff class="App\Sniffs\ParaSniff" />
    </sniffs>
    <paths>
        <path>/absolute/path/to/docs</path>
    </paths>
</docbookcs>
XML;

        $config = $this->parser->parseString($xml, '/some/other/base');

        self::assertSame(['/absolute/path/to/docs'], $config->getIncludePaths());
    }

    #[Test]
    public function itThrowsOnMissingFile(): void
    {
        $this->expectException(ConfigParserException::class);
        $this->expectExceptionMessageIsOrContains('not found');

        $this->parser->parseFile('/nonexistent/docbookcs.xml');
    }

    #[Test]
    public function itThrowsOnInvalidXml(): void
    {
        $this->expectException(ConfigParserException::class);
        $this->expectExceptionMessageIsOrContains('Invalid XML');

        $this->parser->parseString('<broken><xml', '/base');
    }

    #[Test]
    public function itThrowsWhenSniffsElementIsMissing(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<docbookcs>
    <project>
        <directory>en</directory>
    </project>
    <paths><path>foo/</path></paths>
</docbookcs>
XML;

        $this->expectException(ConfigParserException::class);
        $this->expectExceptionMessageIsOrContains('<sniffs>');

        $this->parser->parseString($xml, '/base');
    }

    #[Test]
    public function itThrowsWhenSniffHasNoClassAttribute(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<docbookcs>
    <project>
        <directory>en</directory>
    </project>
    <sniffs>
        <sniff />
    </sniffs>
</docbookcs>
XML;

        $this->expectException(ConfigParserException::class);
        $this->expectExceptionMessageIsOrContains('class');

        $this->parser->parseString($xml, '/base');
    }

    #[Test]
    public function itThrowsWhenPropertyHasNoNameAttribute(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<docbookcs>
    <project>
        <directory>en</directory>
    </project>
    <sniffs>
        <sniff class="App\Sniffs\Foo">
            <property value="bar" />
        </sniff>
    </sniffs>
</docbookcs>
XML;

        $this->expectException(ConfigParserException::class);
        $this->expectExceptionMessageIsOrContains('name');

        $this->parser->parseString($xml, '/base');
    }

    #[Test]
    public function itParsesFromAFixtureFile(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/valid_full.xml';

        if (!is_file($fixturePath)) {
            self::markTestSkipped('Fixture file not found.');
        }

        $config = $this->parser->parseFile($fixturePath);

        self::assertNotEmpty($config->getSniffs());
    }

    #[Test]
    public function itSkipsEmptyPathElements(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<docbookcs>
    <project>
        <directory>en</directory>
    </project>
    <sniffs>
        <sniff class="App\Sniffs\ParaSniff" />
    </sniffs>
    <paths>
        <path>reference/</path>
        <path>   </path>
        <path></path>
    </paths>
</docbookcs>
XML;

        $config = $this->parser->parseString($xml, '/base');

        self::assertCount(1, $config->getIncludePaths());
        self::assertSame('/base/reference', $config->getIncludePaths()[0]);
    }

    #[Test]
    public function itSkipsEmptyEntityElements(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<docbookcs>
    <project>
        <directory>en</directory>
    </project>
    <sniffs>
        <sniff class="App\Sniffs\ParaSniff" />
    </sniffs>
    <entities>
        <directory>entities/</directory>
        <directory>   </directory>
        <file></file>
    </entities>
</docbookcs>
XML;

        $config = $this->parser->parseString($xml, '/base');

        self::assertCount(1, $config->getEntityPaths());
        self::assertSame('/base/entities', $config->getEntityPaths()[0]);
    }

    #[Test]
    public function itRecognizesWindowsAbsolutePaths(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<docbookcs>
    <project>
        <directory>en</directory>
    </project>
    <sniffs>
        <sniff class="App\Sniffs\ParaSniff" />
    </sniffs>
    <paths>
        <path>C:\Users\docs\reference</path>
    </paths>
    <entities>
        <directory>D:/entities</directory>
    </entities>
</docbookcs>
XML;

        $config = $this->parser->parseString($xml, '/base');

        self::assertSame('C:/Users/docs/reference', $config->getIncludePaths()[0]);
        self::assertSame('D:/entities', $config->getEntityPaths()[0]);
    }

    #[Test]
    public function itThrowsWhenFileContainsInvalidXml(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/invalid.xml';

        $this->expectException(ConfigParserException::class);
        $this->expectExceptionMessageIsOrContains('Invalid XML');

        $this->parser->parseFile($fixturePath);
    }

    #[Test]
    public function itParsesProjectRootsWithAlias(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<docbookcs>
    <project>
        <directory alias="doc">en</directory>
        <directory alias="ref">reference</directory>
    </project>
    <sniffs>
        <sniff class="App\Sniffs\ParaSniff" />
    </sniffs>
</docbookcs>
XML;

        $config = $this->parser->parseString($xml, '/project/doc');

        $roots = $config->getProjectRoots();

        self::assertSame('en', $roots['/project/en']);
        self::assertSame('en', $roots['/project/doc']);
        self::assertSame('reference', $roots['/project/reference']);
        self::assertSame('reference', $roots['/project/ref']);
    }
}
