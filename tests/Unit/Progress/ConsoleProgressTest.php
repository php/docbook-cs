<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Progress;

use DocbookCS\Progress\ConsoleProgress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(ConsoleProgress::class),
]
final class ConsoleProgressTest extends TestCase
{
    /** @var resource */
    private $stream;

    /** @throws \RuntimeException if the memory stream cannot be opened. */
    protected function setUp(): void
    {
        $this->stream = fopen('php://memory', 'rwb')
            ?: throw new \RuntimeException('Unable to open memory stream');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    private function outputConsole(): string
    {
        rewind($this->stream);

        return stream_get_contents($this->stream);
    }

    #[Test]
    public function itDisplaysTotalFileCountOnStart(): void
    {
        $progress = new ConsoleProgress($this->stream, useColors: false);

        $progress->start(42);

        self::assertStringContainsString('42 file(s)', $this->outputConsole());
    }

    #[Test]
    public function itShowsPercentageAndCounterOnAdvance(): void
    {
        $progress = new ConsoleProgress($this->stream, useColors: false);

        $progress->start(10);
        $progress->advance(5, '/path/to/file.xml', 0);

        $output = $this->outputConsole();

        self::assertStringContainsString('50%', $output);
        self::assertStringContainsString('(5/10)', $output);
    }

    #[Test]
    public function itShowsCompletionOnFinish(): void
    {
        $progress = new ConsoleProgress($this->stream, useColors: false);

        $progress->start(2);
        $progress->advance(1, 'a.xml', 0);
        $progress->advance(2, 'b.xml', 0);
        $progress->finish();

        $output = $this->outputConsole();

        self::assertStringContainsString('100%', $output);
        self::assertStringContainsString('Done.', $output);
    }

    #[Test]
    public function itDisplaysMessageForZeroFiles(): void
    {
        $progress = new ConsoleProgress($this->stream, useColors: false);

        $progress->start(0);

        self::assertStringContainsString('No files to scan', $this->outputConsole());
    }

    #[Test]
    public function itShowsViolationMarker(): void
    {
        $progress = new ConsoleProgress($this->stream, useColors: false);

        $progress->start(5);
        $progress->advance(1, 'clean.xml', 0);
        $progress->advance(2, 'dirty.xml', 3);

        $output = $this->outputConsole();

        self::assertStringContainsString('.', $output);
        self::assertStringContainsString('x', $output);
    }

    #[Test]
    public function itTruncatesLongPaths(): void
    {
        $progress = new ConsoleProgress($this->stream, useColors: false);

        $longPath = '/very/deeply/nested/directory/structure/that/goes/on/and/on/file.xml';

        $progress->start(1);
        $progress->advance(1, $longPath, 0);

        $output = $this->outputConsole();

        self::assertStringContainsString('...', $output);
        self::assertStringContainsString('file.xml', $output);
    }

    #[Test]
    public function itEmitsAnsiCodesWhenColorsEnabled(): void
    {
        $progress = new ConsoleProgress($this->stream, useColors: true);

        $progress->start(1);
        $progress->advance(1, 'test.xml', 0);
        $progress->finish();

        self::assertStringContainsString("\033[", $this->outputConsole());
    }

    #[Test]
    public function itOmitsAnsiCodesWhenColorsDisabled(): void
    {
        $progress = new ConsoleProgress($this->stream, useColors: false);

        $progress->start(1);
        $progress->advance(1, 'test.xml', 0);
        $progress->finish();

        self::assertStringNotContainsString("\033[", $this->outputConsole());
    }

    #[Test]
    public function itDoesNothingOnAdvanceWhenNoFiles(): void
    {
        $progress = new ConsoleProgress($this->stream, useColors: false);

        $progress->start(0);
        $progress->advance(1, 'file.xml', 0);

        $output = $this->outputConsole();

        self::assertStringContainsString('No files to scan', $output);
        self::assertStringNotContainsString('%', $output);
    }

    #[Test]
    public function itDoesNothingOnFinishWhenNoFiles(): void
    {
        $progress = new ConsoleProgress($this->stream, useColors: false);

        $progress->start(0);
        $progress->finish();

        $output = $this->outputConsole();

        self::assertStringNotContainsString('Done.', $output);
    }

    #[Test]
    public function itFillsBarWhenCurrentEqualsTotal(): void
    {
        $progress = new ConsoleProgress($this->stream, useColors: false);

        $progress->start(3);
        $progress->advance(3, 'done.xml', 0);

        $output = $this->outputConsole();

        self::assertStringContainsString('100%', $output);
        self::assertStringContainsString('(3/3)', $output);
    }

    #[Test]
    public function itDoesNotShowMarkerWhenComplete(): void
    {
        $progress = new ConsoleProgress($this->stream, useColors: false);

        $progress->start(2);
        $progress->advance(2, 'done.xml', 5);

        $output = $this->outputConsole();

        self::assertStringNotContainsString(' x', $output);
        self::assertStringNotContainsString(' .', $output);
    }
}
