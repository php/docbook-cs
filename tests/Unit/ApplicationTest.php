<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit;

use DocbookCS\Application;
use DocbookCS\Config\ConfigData;
use DocbookCS\Config\ConfigParser;
use DocbookCS\Config\ConfigParserException;
use DocbookCS\Config\SniffEntry;
use DocbookCS\Diff\DiffBaseResolver;
use DocbookCS\Diff\DiffChangeset;
use DocbookCS\Diff\DiffParser;
use DocbookCS\Diff\FileChange;
use DocbookCS\Diff\GitDiffProvider;
use DocbookCS\Diff\UpstreamResolver;
use DocbookCS\Git\GitClient;
use DocbookCS\Git\GitException;
use DocbookCS\Path\DiffPathLoader;
use DocbookCS\Path\EntityResolver;
use DocbookCS\Path\PathLoader;
use DocbookCS\Path\PathMatcher;
use DocbookCS\Process\NativeProcessRunner;
use DocbookCS\Process\ProcessResult;
use DocbookCS\Progress\NullProgress;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Report\Reporter\CheckstyleReporter;
use DocbookCS\Report\Reporter\ConsoleReporter;
use DocbookCS\Report\Reporter\JsonReporter;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunCoordinator;
use DocbookCS\Runner\RunPlan;
use DocbookCS\Runner\RunPlanner;
use DocbookCS\Runner\RunScopeResolver;
use DocbookCS\Runner\SourceScope;
use DocbookCS\Runner\ViolationScopeFilter;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Runner\XmlProcessingResult;
use DocbookCS\Sniff\ExceptionNameSniff;
use DocbookCS\Source\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(Application::class),
    CoversClass(CheckstyleReporter::class),
    CoversClass(ConfigData::class),
    CoversClass(ConfigParser::class),
    CoversClass(ConfigParserException::class),
    CoversClass(ConsoleReporter::class),
    CoversClass(DiffParser::class),
    CoversClass(EntityPreprocessor::class),
    CoversClass(EntityResolver::class),
    CoversClass(ExceptionNameSniff::class),
    CoversClass(FileReport::class),
    CoversClass(JsonReporter::class),
    CoversClass(NullProgress::class),
    CoversClass(PathLoader::class),
    CoversClass(PathMatcher::class),
    CoversClass(Report::class),
    CoversClass(RunCoordinator::class),
    CoversClass(RunMode::class),
    CoversClass(RunPlan::class),
    CoversClass(RunPlanner::class),
    CoversClass(SniffEntry::class),
    CoversClass(XmlFileProcessor::class),
    //
    UsesClass(DiffBaseResolver::class),
    UsesClass(DiffChangeset::class),
    UsesClass(DiffPathLoader::class),
    UsesClass(File::class),
    UsesClass(FileChange::class),
    UsesClass(GitClient::class),
    UsesClass(GitDiffProvider::class),
    UsesClass(GitException::class),
    UsesClass(NativeProcessRunner::class),
    UsesClass(ProcessResult::class),
    UsesClass(RunScopeResolver::class),
    UsesClass(SourceScope::class),
    UsesClass(UpstreamResolver::class),
    UsesClass(ViolationScopeFilter::class),
    UsesClass(XmlProcessingResult::class),
]
final class ApplicationTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/../fixtures/application';
    private const string VALID_CONFIG = self::FIXTURE_DIR . '/valid_config.xml';
    private const string INVALID_SNIFF_CONFIG = self::FIXTURE_DIR . '/invalid_sniff_config.xml';
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

    /** @param resource $stream */
    private function readStream(mixed $stream): string
    {
        rewind($stream);

        return stream_get_contents($stream) ?: '';
    }

    #[Test] // TODO: should be feature
    public function itPrintsHelpAndExitsWithZero(): void
    {
        $app = new Application(['docbook-cs', '--help'], $this->stdout, $this->stderr);

        $exitCode = $app->run();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Usage:', $this->readStream($this->stdout));
        self::assertSame('', $this->readStream($this->stderr));
    }

    #[Test] // TODO: should be feature
    public function itPrintsVersionAndExitsWithZero(): void
    {
        $app = new Application(['docbook-cs', '--version'], $this->stdout, $this->stderr);

        $exitCode = $app->run();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('DocbookCS version', $this->readStream($this->stdout));
        self::assertSame('', $this->readStream($this->stderr));
    }

    #[Test] // TODO: should be feature
    public function itReturnsErrorWhenConfigCannotBeLoaded(): void
    {
        $app = new Application(
            ['docbook-cs', '--config=nonexistent.xml'],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Error:', $this->readStream($this->stderr));
    }

    #[Test] // TODO: should be feature
    public function itHandlesSeparateConfigArgument(): void
    {
        $app = new Application(
            ['docbook-cs', '--config', 'nonexistent.xml'],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Error:', $this->readStream($this->stderr));
    }

    #[Test] // TODO: should be feature
    public function itAcceptsPathsWithoutCrashing(): void
    {
        $app = new Application(
            ['docbook-cs', 'file.xml', 'dir/file.xml'],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        self::assertContains($exitCode, [0, 1, 2]);
    }

    #[Test] // TODO: should be feature
    public function itSupportsQuietFlag(): void
    {
        $app = new Application(['docbook-cs', '--quiet'], $this->stdout, $this->stderr);

        $exitCode = $app->run();

        self::assertContains($exitCode, [0, 1, 2]);
    }

    #[Test] // TODO: should be feature
    public function itSupportsReportFormats(): void
    {
        foreach (['console', 'json', 'checkstyle'] as $format) {
            $app = new Application(
                ['docbook-cs', "--report={$format}"],
                $this->stdout,
                $this->stderr,
            );

            $exitCode = $app->run();

            self::assertContains($exitCode, [0, 1, 2]);
        }
    }

    #[Test] // TODO: should be feature
    public function itSupportsColorFlags(): void
    {
        foreach (['--colors', '--no-colors'] as $flag) {
            $app = new Application(
                ['docbook-cs', $flag],
                $this->stdout,
                $this->stderr,
            );

            $exitCode = $app->run();

            self::assertContains($exitCode, [0, 1, 2]);
        }
    }

    #[Test] // TODO: should be feature
    public function helpShortCircuitsExecution(): void
    {
        $app = new Application(
            ['docbook-cs', '--help', '--config=invalid.xml'],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Usage:', $this->readStream($this->stdout));
        self::assertSame('', $this->readStream($this->stderr));
    }

    #[Test] // TODO: should be feature
    public function versionShortCircuitsExecution(): void
    {
        $app = new Application(
            ['docbook-cs', '--version', '--config=invalid.xml'],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('DocbookCS version', $this->readStream($this->stdout));
        self::assertSame('', $this->readStream($this->stderr));
    }

    #[Test] // TODO: should be feature
    public function itResolvesRelativeOverridePathsAgainstCwd(): void
    {
        $app = new Application(
            ['docbook-cs', '--config=' . self::VALID_CONFIG, self::SCAN_FILE, 'relative/path.xml'],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        // The app should not crash with exit code 2 (config/runtime error).
        // It may return 0 (no violations) or 1 (violations).
        self::assertNotSame(2, $exitCode);
    }

    #[Test] // TODO: should be feature
    public function itCatchesRuntimeErrorFromRunner(): void
    {
        $app = new Application(
            ['docbook-cs', '--config=' . self::INVALID_SNIFF_CONFIG, self::SCAN_FILE],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Runtime error:', $this->readStream($this->stderr));
    }

    #[Test] // TODO: should be feature
    public function itSupportsSeparateReportArgument(): void
    {
        $app = new Application(
            ['docbook-cs', '--report', 'json'],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        self::assertContains($exitCode, [0, 1, 2]);
    }

    #[Test] // TODO: should be feature
    public function itPassesThroughAbsoluteOverridePaths(): void
    {
        $app = new Application(
            ['docbook-cs', '--config=' . self::VALID_CONFIG, self::SCAN_FILE],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        self::assertNotSame(2, $exitCode);
    }

    #[Test] // TODO: should be feature
    public function itSuppressesProgressWhenQuietFlagIsSet(): void
    {
        $app = new Application(
            ['docbook-cs', '--config=' . self::VALID_CONFIG, '--quiet', self::SCAN_FILE],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        self::assertNotSame(2, $exitCode);
        self::assertSame('', $this->readStream($this->stderr));
    }

    #[Test] // TODO: should be feature
    public function itSuppressesProgressForStructuredReportFormats(): void
    {
        foreach (['json', 'checkstyle'] as $format) {
            $stdout = fopen('php://memory', 'wb+');
            $stderr = fopen('php://memory', 'wb+');

            self::assertIsResource($stdout);
            self::assertIsResource($stderr);

            $app = new Application(
                ['docbook-cs', '--config=' . self::VALID_CONFIG, "--report={$format}", self::SCAN_FILE],
                $stdout,
                $stderr,
            );

            $exitCode = $app->run();

            self::assertNotSame(2, $exitCode);
            self::assertSame('', $this->readStream($stderr), "stderr should be empty for --report={$format}");
        }
    }

    #[Test] // TODO: should be feature
    public function itShowsPerformanceWhenPerfFlagIsEnabled(): void
    {
        $app = new Application(
            [
                'docbook-cs',
                '--config=' . self::VALID_CONFIG,
                '--perf',
                self::SCAN_FILE,
            ],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        self::assertNotSame(2, $exitCode);

        $output = $this->readStream($this->stdout);

        self::assertStringContainsString('PERFORMANCE', $output);
    }

    #[Test] // TODO: should be feature
    public function itDoesNotShowPerformanceByDefault(): void
    {
        $app = new Application(
            [
                'docbook-cs',
                '--config=' . self::VALID_CONFIG,
                self::SCAN_FILE,
            ],
            $this->stdout,
            $this->stderr,
        );

        $app->run();

        $output = $this->readStream($this->stdout);

        self::assertStringNotContainsString('PERFORMANCE', $output);
    }
}
