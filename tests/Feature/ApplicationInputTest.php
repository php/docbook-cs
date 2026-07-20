<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Feature;

use DocbookCS\Application;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ApplicationInputTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/../fixtures/application';
    private const string VALID_CONFIG = self::FIXTURE_DIR . '/valid_config.xml';
    private const string SCAN_FILE = self::FIXTURE_DIR . '/scan_target/book.xml';

    /** @var resource */
    private mixed $stdout;

    /** @var resource */
    private mixed $stderr;

    protected function setUp(): void
    {
        $stdout = fopen('php://memory', 'wb+');
        $stderr = fopen('php://memory', 'wb+');

        if (!is_resource($stdout) || !is_resource($stderr)) {
            throw new \RuntimeException('Failed to create memory streams for testing.');
        }

        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    #[Test]
    public function itRejectsTheRemovedDiffOption(): void
    {
        $app = new Application(
            ['docbook-cs', '--config=' . self::VALID_CONFIG, '--diff'],
            $this->stdout,
            $this->stderr,
        );

        self::assertSame(2, $app->run());
        self::assertStringContainsString('Unknown option: --diff', $this->readStream($this->stderr));
    }

    #[Test]
    public function itDetectsAPipedDiffWithoutAFlag(): void
    {
        $app = new Application(
            ['docbook-cs', '--config=' . self::VALID_CONFIG],
            $this->stdout,
            $this->stderr,
            stdin: '',
        );

        self::assertSame(0, $app->run());
        self::assertSame('', $this->readStream($this->stderr));
    }

    #[Test]
    public function itRejectsPathsCombinedWithAPipedDiff(): void
    {
        $app = new Application(
            ['docbook-cs', '--config=' . self::VALID_CONFIG, self::SCAN_FILE],
            $this->stdout,
            $this->stderr,
            stdin: '',
        );

        self::assertSame(2, $app->run());
        self::assertStringContainsString('Paths cannot be combined with diff input', $this->readStream($this->stderr));
    }

    #[Test]
    public function itIncludesTheWideOptionInHelp(): void
    {
        $app = new Application(['docbook-cs', '--help'], $this->stdout, $this->stderr);

        $app->run();

        $output = $this->readStream($this->stdout);

        self::assertStringContainsString('--wide', $output);
        self::assertStringNotContainsString('--diff', $output);
    }

    /** @param resource $stream */
    private function readStream(mixed $stream): string
    {
        rewind($stream);

        return stream_get_contents($stream) ?: '';
    }
}
