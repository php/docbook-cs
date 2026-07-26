<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fixer\AttributeOrderFixer;
use DocbookCS\Fix\Fixer\ExceptionNameFixer;
use DocbookCS\Fix\Fixer\Fixer;
use DocbookCS\Fix\Fixer\MixedIndentationFixer;
use DocbookCS\Fix\Fixer\SimparaFixer;
use DocbookCS\Fix\Fixer\TrailingWhitespaceFixer;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(AttributeOrderFixer::class),
    CoversClass(ExceptionNameFixer::class),
    CoversClass(FixerException::class),
    CoversClass(MixedIndentationFixer::class),
    CoversClass(SimparaFixer::class),
    CoversClass(TrailingWhitespaceFixer::class),
    //
    UsesClass(SourceRange::class),
    UsesClass(Violation::class),
]
final class FixerInputValidationTest extends TestCase
{
    /** @param non-empty-list<SourceRange> $ranges */
    #[Test, DataProvider('missingContent')]
    public function itRejectsAffectedRangesWithoutSourceContent(Fixer $fixer, array $ranges): void
    {
        $this->expectException(FixerException::class);
        $this->expectExceptionMessageIs('Fixers require affected source ranges with source content.');

        $fixer->process($this->violation($ranges));
    }

    /** @return iterable<string, array{Fixer, non-empty-list<SourceRange>}> */
    public static function missingContent(): iterable
    {
        yield 'attribute order' => [new AttributeOrderFixer(), [new SourceRange(1, 0, 4)]];
        yield 'exception name' => [new ExceptionNameFixer(), [
            new SourceRange(1, 0, 9),
            new SourceRange(1, 10, 19),
        ]];
        yield 'mixed indentation' => [new MixedIndentationFixer(), [new SourceRange(1, 0, 2)]];
        yield 'simpara' => [new SimparaFixer(), [
            new SourceRange(1, 0, 4),
            new SourceRange(1, 5, 9),
        ]];
        yield 'trailing whitespace' => [new TrailingWhitespaceFixer(), [new SourceRange(1, 0, 2)]];
    }

    /** @param non-empty-list<SourceRange> $ranges */
    #[Test, DataProvider('invalidContent')]
    public function itRejectsInvalidSourceContent(Fixer $fixer, array $ranges): void
    {
        $this->expectException(FixerException::class);
        $m = 'Cannot fix violation T.Sniff in f.xml on line 1 because its source content is not valid fixer input.';
        $this->expectExceptionMessageIs($m);

        $fixer->process($this->violation($ranges));
    }

    /** @return iterable<string, array{Fixer, non-empty-list<SourceRange>}> */
    public static function invalidContent(): iterable
    {
        yield 'attribute order' => [new AttributeOrderFixer(), [new SourceRange(1, 0, 6, '<tag/>')]];
        yield 'exception name' => [new ExceptionNameFixer(), [
            new SourceRange(1, 0, 5, 'class'),
            new SourceRange(1, 6, 11, 'class'),
        ]];
        yield 'mixed indentation' => [new MixedIndentationFixer(), [new SourceRange(1, 0, 2, '  ')]];
        yield 'simpara' => [new SimparaFixer(), [
            new SourceRange(1, 0, 4, 'span'),
            new SourceRange(1, 5, 9, 'span'),
        ]];
        yield 'trailing whitespace' => [new TrailingWhitespaceFixer(), [new SourceRange(1, 0, 4, 'text')]];
    }

    #[Test, DataProvider('pairedElementFixers')]
    public function itRejectsIncompletePairedElementRanges(Fixer $fixer): void
    {
        $this->expectException(FixerException::class);

        $fixer->process($this->violation([new SourceRange(1, 0, 4, 'para')]));
    }

    /** @return iterable<string, array{Fixer}> */
    public static function pairedElementFixers(): iterable
    {
        yield 'exception name' => [new ExceptionNameFixer()];
        yield 'simpara' => [new SimparaFixer()];
    }

    #[Test]
    public function itRejectsMalformedAttributeOrderInput(): void
    {
        $this->expectException(FixerException::class);

        new AttributeOrderFixer()->process($this->violation([
            new SourceRange(1, 0, 9, 'not a tag'),
        ]));
    }

    #[Test]
    public function itRejectsAttributeOrderInputThatIsAlreadyOrdered(): void
    {
        $content = '<tag xml:id="id" xmlns="urn:test">';

        $this->expectException(FixerException::class);

        new AttributeOrderFixer()->process($this->violation([
            new SourceRange(1, 0, strlen($content), $content),
        ]));
    }

    /** @param non-empty-list<SourceRange> $ranges */
    private function violation(array $ranges): Violation
    {
        return new Violation('T.Sniff', 'f.xml', 'violation.', $ranges);
    }
}
