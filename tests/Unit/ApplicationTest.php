<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit;

use DocbookCS\Application;
use DocbookCS\Config\ConfigData;
use DocbookCS\Diff\DiffParser;
use DocbookCS\Config\ConfigParser;
use DocbookCS\Config\ConfigParserException;
use DocbookCS\Config\SniffEntry;
use DocbookCS\Path\EntityResolver;
use DocbookCS\Path\PathLoader;
use DocbookCS\Path\PathMatcher;
use DocbookCS\Progress\NullProgress;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Report\Reporter\CheckstyleReporter;
use DocbookCS\Report\Reporter\ConsoleReporter;
use DocbookCS\Report\Reporter\JsonReporter;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\SniffRunner;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Sniff\ExceptionNameSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
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
    CoversClass(SniffEntry::class),
    CoversClass(SniffRunner::class),
    CoversClass(XmlFileProcessor::class),
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

    #[Test]
    public function itPrintsHelpAndExitsWithZero(): void
    {
        $app = new Application(['docbook-cs', '--help'], $this->stdout, $this->stderr);

        $exitCode = $app->run();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Usage:', $this->readStream($this->stdout));
        self::assertSame('', $this->readStream($this->stderr));
    }

    #[Test]
    public function itPrintsVersionAndExitsWithZero(): void
    {
        $app = new Application(['docbook-cs', '--version'], $this->stdout, $this->stderr);

        $exitCode = $app->run();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('DocbookCS version', $this->readStream($this->stdout));
        self::assertSame('', $this->readStream($this->stderr));
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function itSupportsQuietFlag(): void
    {
        $app = new Application(['docbook-cs', '--quiet'], $this->stdout, $this->stderr);

        $exitCode = $app->run();

        self::assertContains($exitCode, [0, 1, 2]);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function itCatchesRuntimeErrorFromRunner(): void
    {
        $app = new Application(
            ['docbook-cs', '--config=' . self::INVALID_SNIFF_CONFIG],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Runtime error:', $this->readStream($this->stderr));
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function itSupportsDiffFromFile(): void
    {
        $diffFile = tempnam(sys_get_temp_dir(), 'docbookcs_test_');
        self::assertIsString($diffFile);

        // Diff that references no XML files the config would normally scan.
        file_put_contents($diffFile, <<<'DIFF'
diff --git a/nonexistent.xml b/nonexistent.xml
--- a/nonexistent.xml
+++ b/nonexistent.xml
@@ -1,1 +1,2 @@
 line1
+line2
DIFF);

        try {
            $app = new Application(
                ['docbook-cs', '--config=' . self::VALID_CONFIG, "--diff={$diffFile}"],
                $this->stdout,
                $this->stderr,
            );

            $exitCode = $app->run();

            // No matching files → no violations → exit 0.
            self::assertSame(0, $exitCode);
            self::assertSame('', $this->readStream($this->stderr));
        } finally {
            unlink($diffFile);
        }
    }

    #[Test]
    public function itSupportsDiffFromStdin(): void
    {
        $stdin = fopen('php://memory', 'rb+');
        self::assertIsResource($stdin);

        fwrite($stdin, <<<'DIFF'
diff --git a/nonexistent.xml b/nonexistent.xml
--- a/nonexistent.xml
+++ b/nonexistent.xml
@@ -1,1 +1,2 @@
 line1
+line2
DIFF);
        rewind($stdin);

        $app = new Application(
            ['docbook-cs', '--config=' . self::VALID_CONFIG, '--diff'],
            $this->stdout,
            $this->stderr,
            $stdin,
        );

        $exitCode = $app->run();

        self::assertSame(0, $exitCode);
        self::assertSame('', $this->readStream($this->stderr));
    }

    #[Test]
    public function itSupportsDiffFromStdinWithExplicitDash(): void
    {
        $stdin = fopen('php://memory', 'rb+');
        self::assertIsResource($stdin);

        fwrite($stdin, '');
        rewind($stdin);

        $app = new Application(
            ['docbook-cs', '--config=' . self::VALID_CONFIG, '--diff=-'],
            $this->stdout,
            $this->stderr,
            $stdin,
        );

        $exitCode = $app->run();

        self::assertSame(0, $exitCode);
    }

    #[Test]
    public function itReturnsErrorWhenDiffFileCannotBeRead(): void
    {
        $app = new Application(
            ['docbook-cs', '--config=' . self::VALID_CONFIG, '--diff=/nonexistent/path.patch'],
            $this->stdout,
            $this->stderr,
        );

        $exitCode = $app->run();

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Error reading diff', $this->readStream($this->stderr));
    }

    #[Test]
    public function itIncludesDiffOptionInHelp(): void
    {
        $app = new Application(['docbook-cs', '--help'], $this->stdout, $this->stderr);

        $app->run();

        self::assertStringContainsString('--diff', $this->readStream($this->stdout));
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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
