<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Source;

use DocbookCS\IndentationAnalyzer;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(IndentationAnalyzer::class),
    //
    UsesClass(File::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
]
final class IndentationAnalyzerTest extends TestCase
{
    #[Test]
    public function itFindsEveryIndentationMismatchFromIssue47(): void
    {
        self::assertSame([
            [6, '      ', 4],
            [7, '      ', 4],
            [13, '      ', 4],
            [14, '        ', 4],
            [15, '', 5],
            [22, '    ', 3],
            [32, "\t   ", 6],
            [33, "\t   ", 6],
            [34, "\t ", 5],
        ], $this->mismatches($this->fixture('issue_47.xml')));
    }

    /** @param list<array{int, string, int}> $expected */
    #[Test, DataProvider('structuralCases')]
    public function itTracksStructuralContext(string $content, array $expected): void
    {
        self::assertSame($expected, $this->mismatches($content));
    }

    /** @return iterable<string, array{string, list<array{int, string, int}>}> */
    public static function structuralCases(): iterable
    {
        yield 'xml:space default resets inherited preservation' => [
            <<<'XML'
<root xml:space="preserve">
 <section xml:space="default">
<para />
 </section>
</root>
XML,
            [[3, '', 2]],
        ];

        yield 'preserved content is ignored but its boundaries are checked' => [
            <<<'XML'
<root>
  <programlisting>
unstructured preserved content
    </programlisting>
</root>
XML,
            [
                [2, '  ', 1],
                [4, '    ', 1],
            ],
        ];

        yield 'self-closing elements do not increase the depth' => [
            <<<'XML'
<root>
 <first />
  <second />
</root>
XML,
            [[3, '  ', 1]],
        ];

        yield 'namespace prefixes do not prevent verbatim preservation' => [
            <<<'XML'
<db:root xmlns:db="urn:test">
 <db:programlisting>
unstructured preserved content
 </db:programlisting>
</db:root>
XML,
            [],
        ];

        yield 'all tags on a line affect following lines' => [
            <<<'XML'
<root><wrapper>
 <child />
</wrapper></root>
XML,
            [
                [2, ' ', 2],
                [3, '', 1],
            ],
        ];

        yield 'whitespace-only lines are ignored' => [
            "<root>\n \t  \n</root>",
            [],
        ];
    }

    /** @return list<array{int, string, int}> */
    private function mismatches(string $content): array
    {
        $mismatches = [];

        foreach (new IndentationAnalyzer()->analyze(new File('file.xml', $content)) as $mismatch) {
            self::assertNotNull($mismatch['range']->content);

            $mismatches[] = [
                $mismatch['range']->line,
                $mismatch['range']->content,
                $mismatch['expectedDepth'],
            ];
        }

        return $mismatches;
    }

    private function fixture(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/indentation/' . $name);
        self::assertIsString($content);

        return $content;
    }
}
