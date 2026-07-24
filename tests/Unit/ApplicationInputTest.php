<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit;

use DocbookCS\Application;
use DocbookCS\Config\ConfigData;
use DocbookCS\Config\ConfigParser;
use DocbookCS\Config\SniffEntry;
use DocbookCS\Diff\Diff;
use DocbookCS\Diff\DiffParser;
use DocbookCS\Diff\GitDiffProvider;
use DocbookCS\Path\DiffPathLoader;
use DocbookCS\Path\EntityResolver;
use DocbookCS\Path\PathMatcher;
use DocbookCS\Progress\NullProgress;
use DocbookCS\Report\Report;
use DocbookCS\Report\Reporter\ConsoleReporter;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\RunPlan;
use DocbookCS\Runner\RunPlanner;
use DocbookCS\Runner\RunScopeResolver;
use DocbookCS\Runner\SniffRunner;
use DocbookCS\Runner\XmlFileProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(Application::class),
    //
    UsesClass(ConfigData::class),
    UsesClass(ConfigParser::class),
    UsesClass(ConsoleReporter::class),
    UsesClass(Diff::class),
    UsesClass(DiffParser::class),
    UsesClass(DiffPathLoader::class),
    UsesClass(EntityPreprocessor::class),
    UsesClass(EntityResolver::class),
    UsesClass(GitDiffProvider::class),
    UsesClass(NullProgress::class),
    UsesClass(PathMatcher::class),
    UsesClass(Report::class),
    UsesClass(RunPlan::class),
    UsesClass(RunPlanner::class),
    UsesClass(RunScopeResolver::class),
    UsesClass(SniffEntry::class),
    UsesClass(SniffRunner::class),
    UsesClass(XmlFileProcessor::class),
]
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
            unifiedDiff: '',
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
            unifiedDiff: '',
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

    #[Test]
    public function itAcceptsTheWideOption(): void
    {
        $app = new Application(
            ['docbook-cs', '--config=' . self::VALID_CONFIG, '--wide'],
            $this->stdout,
            $this->stderr,
            unifiedDiff: '',
        );

        self::assertSame(0, $app->run());
        self::assertSame('', $this->readStream($this->stderr));
    }

    /** @param resource $stream */
    private function readStream(mixed $stream): string
    {
        rewind($stream);

        return stream_get_contents($stream) ?: '';
    }
}
