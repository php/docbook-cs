<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Runner\EntityPreprocessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(EntityPreprocessor::class),
]
final class EntityPreprocessorTest extends TestCase
{
    #[Test]
    public function itPreservesPredefinedXmlEntities(): void
    {
        $preprocessor = new EntityPreprocessor([]);

        $input = '<simpara>1 &lt; 2 &amp; 3 &gt; 0 &quot;hi&quot; &apos;yo&apos;</simpara>';

        $result = $preprocessor->process($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function itExpandsKnownEntities(): void
    {
        $preprocessor = new EntityPreprocessor([
            'php.ini' => 'php.ini',
            'configuration.file' => 'configuration file',
        ]);

        $input = '<simpara>&php.ini; is a &configuration.file; thing.</simpara>';

        $result = $preprocessor->process($input);

        self::assertSame('<simpara>php.ini is a configuration file thing.</simpara>', $result);
    }

    #[Test]
    public function itLeavesUnknownEntitiesUnchanged(): void
    {
        $preprocessor = new EntityPreprocessor([]);

        $input = '<simpara>&unknown.entity; stays.</simpara>';

        $result = $preprocessor->process($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function itPreservesNumericCharacterReferences(): void
    {
        $preprocessor = new EntityPreprocessor([]);

        $input = '<simpara>&#169; &#x00A9;</simpara>';

        $result = $preprocessor->process($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function itHandlesMixedEntityTypes(): void
    {
        $preprocessor = new EntityPreprocessor([
            'custom.entity' => 'replaced',
        ]);

        $input = '<simpara>&amp; &custom.entity; &#169; &lt;</simpara>';

        $result = $preprocessor->process($input);

        self::assertSame('<simpara>&amp; replaced &#169; &lt;</simpara>', $result);
    }

    #[Test]
    public function itReturnsUnchangedContentWithNoEntities(): void
    {
        $preprocessor = new EntityPreprocessor([]);

        $input = '<simpara>No entities here.</simpara>';

        $result = $preprocessor->process($input);

        self::assertSame($input, $result);
    }

    /** @param array<string, string> $entities */
    #[Test]
    #[DataProvider('entityNameProvider')]
    public function itHandlesVariousEntityNameFormats(string $input, string $expected, array $entities): void
    {
        $preprocessor = new EntityPreprocessor($entities);

        $result = $preprocessor->process($input);

        self::assertSame($expected, $result);
    }

    /** @return array<string, array{string, string, array<string, string>}> */
    public static function entityNameProvider(): array
    {
        return [
            'simple' => ['&foo;', 'FOO', ['foo' => 'FOO']],
            'dotted' => ['&foo.bar;', 'FOOBAR', ['foo.bar' => 'FOOBAR']],
            'hyphenated' => ['&foo-bar;', 'FOOB', ['foo-bar' => 'FOOB']],
            'underscored' => ['&foo_bar;', 'FB', ['foo_bar' => 'FB']],
            'unknown' => ['&unknown;', '&unknown;', []],
            'predefined amp' => ['&amp;', '&amp;', []],
            'predefined lt' => ['&lt;', '&lt;', []],
            'numeric decimal' => ['&#8212;', '&#8212;', []],
            'numeric hex' => ['&#x2014;', '&#x2014;', []],
        ];
    }

    #[Test]
    public function itStripsSimpleDoctype(): void
    {
        $preprocessor = new EntityPreprocessor([]);

        $input = '<?xml version="1.0"?><!DOCTYPE book SYSTEM "docbook.dtd"><book/>';

        $result = $preprocessor->process($input);

        self::assertSame('<?xml version="1.0"?><book/>', $result);
    }

    #[Test]
    public function itStripsDoctypeWithInternalSubset(): void
    {
        $preprocessor = new EntityPreprocessor([]);

        $input = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE book [
  <!ENTITY foo "bar">
  <!ENTITY baz '<link linkend="x">text</link>'>
]>
<book/>
XML;

        $result = $preprocessor->process($input);

        self::assertStringNotContainsString('DOCTYPE', $result);
        self::assertStringNotContainsString('ENTITY', $result);
        self::assertStringContainsString('<book/>', $result);
    }

    #[Test]
    public function itLeavesContentUnchangedWhenNoDoctypePresent(): void
    {
        $preprocessor = new EntityPreprocessor([]);

        $input = '<?xml version="1.0"?><book/>';

        $result = $preprocessor->process($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function itStripsDoctypeContainingQuotedAngleBrackets(): void
    {
        $preprocessor = new EntityPreprocessor([]);

        $input = '<!DOCTYPE book [ <!ENTITY x "<simpara>hi</simpara>"> ]><book/>';

        $result = $preprocessor->process($input);

        self::assertSame('<book/>', $result);
    }

    #[Test]
    public function itProducesParseableXmlFromFullPipeline(): void
    {
        $preprocessor = new EntityPreprocessor([
            'link.superglobals' => '<link linkend="language.variables.superglobals">superglobals</link>',
            'php.ini' => 'php.ini',
        ]);

        $input = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE chapter [
  <!ENTITY link.superglobals '<link linkend="language.variables.superglobals">superglobals</link>'>
  <!ENTITY php.ini "php.ini">
]>
<chapter>
  <simpara>&link.superglobals; and &php.ini; and &amp; done.</simpara>
</chapter>
XML;

        $result = $preprocessor->process($input);

        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($result);

        self::assertTrue($loaded, 'Preprocessed XML should parse without errors.');
    }

    #[Test]
    public function itStripsDoctypeAndExpandsEntitiesInProcess(): void
    {
        $preprocessor = new EntityPreprocessor([
            'link.superglobals' => '<link>superglobals</link>',
        ]);

        $input = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE chapter SYSTEM "docbook.dtd">
<chapter>
  <simpara>&link.superglobals; are &amp; special.</simpara>
</chapter>
XML;

        $result = $preprocessor->process($input);

        self::assertStringNotContainsString('DOCTYPE', $result);
        self::assertStringNotContainsString('&link.superglobals;', $result);
        self::assertStringContainsString('<link>superglobals</link>', $result);
        self::assertStringContainsString('&amp;', $result);
        self::assertStringContainsString('<chapter>', $result);
    }

    #[Test]
    public function itReturnsOriginalContentWhenDoctypeIsNotClosed(): void
    {
        $preprocessor = new EntityPreprocessor([]);

        $input = '<!DOCTYPE book [ <!ENTITY foo "bar" ><book/>';

        $result = $preprocessor->process($input);

        // stripDoctype returns original when unclosed, then expandEntities runs but no known entities
        self::assertSame($input, $result);
    }

    #[Test]
    public function itResolvesNestedEntities(): void
    {
        $preprocessor = new EntityPreprocessor([
            'inner' => 'INNER_VALUE',
            'outer' => 'before &inner; after',
        ]);

        $input = '<root>&outer;</root>';

        $result = $preprocessor->process($input);

        self::assertSame('<root>before INNER_VALUE after</root>', $result);
    }

    #[Test]
    public function itStripsXmlDeclarationFromExpandedEntityValues(): void
    {
        $preprocessor = new EntityPreprocessor([
            'included' => '<?xml version="1.0" encoding="UTF-8"?><para>included content</para>',
        ]);

        $input = '<root>&included;</root>';

        $result = $preprocessor->process($input);

        self::assertStringNotContainsString('<?xml', $result);
        self::assertStringContainsString('<para>included content</para>', $result);
    }

    #[Test]
    public function itPreservesXmlComments(): void
    {
        $preprocessor = new EntityPreprocessor([
            'foo' => 'expanded',
        ]);

        $input = '<root><!-- &foo; should not expand -->&foo;</root>';

        $result = $preprocessor->process($input);

        self::assertStringContainsString('<!-- &foo; should not expand -->', $result);
        self::assertStringContainsString('expanded', $result);
    }

    #[Test]
    public function itHandlesMaxDepthWithoutInfiniteLoop(): void
    {
        // Create a self-referencing entity (would loop forever without depth limit)
        $preprocessor = new EntityPreprocessor([
            'loop' => '&loop;',
        ]);

        $input = '<root>&loop;</root>';

        // Should terminate without hanging due to the maxDepth guard
        $result = $preprocessor->process($input);

        self::assertIsString($result);
    }
}
