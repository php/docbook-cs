<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit;

use DocbookCS\Application;
use DocbookCS\Config\ConfigData;
use DocbookCS\Config\ConfigParser;
use DocbookCS\Config\SniffEntry;
use DocbookCS\Diff\DiffBaseResolver;
use DocbookCS\Diff\DiffChangeset;
use DocbookCS\Diff\DiffParser;
use DocbookCS\Diff\GitDiffProvider;
use DocbookCS\Diff\UpstreamResolver;
use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixResult;
use DocbookCS\Fix\Fixer\ExceptionNameFixer;
use DocbookCS\Git\GitClient;
use DocbookCS\Git\GitException;
use DocbookCS\Path\DiffPathLoader;
use DocbookCS\Path\EntityResolver;
use DocbookCS\Path\PathLoader;
use DocbookCS\Path\PathMatcher;
use DocbookCS\Progress\NullProgress;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Report\Reporter\ConsoleReporter;
use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\RunCoordinator;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunPlan;
use DocbookCS\Runner\RunPlanner;
use DocbookCS\Runner\RunScope;
use DocbookCS\Runner\RunScopeResolver;
use DocbookCS\Runner\ViolationScopeFilter;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Runner\XmlFixRunner;
use DocbookCS\Runner\XmlSniffRunner;
use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Sniff\ExceptionNameSniff;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(Application::class),
    //
    UsesClass(AbstractSniff::class),
    UsesClass(ConfigData::class),
    UsesClass(ConfigParser::class),
    UsesClass(ConsoleReporter::class),
    UsesClass(DiffBaseResolver::class),
    UsesClass(DiffChangeset::class),
    UsesClass(DiffParser::class),
    UsesClass(DiffPathLoader::class),
    UsesClass(EntityExpansionMarker::class),
    UsesClass(EntityPreprocessor::class),
    UsesClass(EntityResolver::class),
    UsesClass(ExceptionNameFixer::class),
    UsesClass(ExceptionNameSniff::class),
    UsesClass(File::class),
    UsesClass(FileReport::class),
    UsesClass(Fix::class),
    UsesClass(FixApplier::class),
    UsesClass(FixPlan::class),
    UsesClass(FixResult::class),
    UsesClass(GitClient::class),
    UsesClass(GitDiffProvider::class),
    UsesClass(GitException::class),
    UsesClass(NullProgress::class),
    UsesClass(PathLoader::class),
    UsesClass(PathMatcher::class),
    UsesClass(Report::class),
    UsesClass(RunCoordinator::class),
    UsesClass(RunMode::class),
    UsesClass(RunPlan::class),
    UsesClass(RunPlanner::class),
    UsesClass(RunScope::class),
    UsesClass(RunScopeResolver::class),
    UsesClass(SniffEntry::class),
    UsesClass(SourceRange::class),
    UsesClass(UpstreamResolver::class),
    UsesClass(Violation::class),
    UsesClass(ViolationScopeFilter::class),
    UsesClass(XmlFileProcessor::class),
    UsesClass(XmlFixRunner::class),
    UsesClass(XmlSniffRunner::class),
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
    public function itRejectsUnknownOptions(): void
    {
        $app = new Application(
            ['docbook-cs', '--config=' . self::VALID_CONFIG, '--widde'],
            $this->stdout,
            $this->stderr,
        );

        self::assertSame(2, $app->run());
        self::assertStringContainsString(
            'Unknown option: --widde',
            $this->readStream($this->stderr),
        );
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
    public function itDetectsPipedDiffThroughCliEntryPoint(): void
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/../../bin/docbook-cs', '--config=' . self::VALID_CONFIG, self::SCAN_FILE],
            [
                ['pipe', 'r'],
                ['pipe', 'w'],
                ['pipe', 'w'],
            ],
            $pipes,
            getcwd() ?: '.',
        );
        self::assertIsResource($process);
        self::assertCount(3, $pipes);
        self::assertIsResource($pipes[0]);
        self::assertIsResource($pipes[1]);
        self::assertIsResource($pipes[2]);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertSame(2, proc_close($process), $stdout ?: '');
        self::assertIsString($stderr);
        self::assertStringContainsString('Paths cannot be combined with diff input', $stderr);
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
    public function itIncludesFixAndWideOptionsInHelp(): void
    {
        $app = new Application(['docbook-cs', '--help'], $this->stdout, $this->stderr);

        $app->run();

        $output = $this->readStream($this->stdout);

        self::assertStringContainsString('--fix', $output);
        self::assertStringContainsString('--wide', $output);
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

    #[Test]
    public function itAppliesFixesInFixMode(): void
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'docbook-cs-');
        self::assertIsString($temporaryPath);
        $filePath = $temporaryPath . '.xml';
        rename($temporaryPath, $filePath);
        file_put_contents($filePath, '<root><classname>RuntimeException</classname></root>');

        try {
            $app = new Application(
                ['docbook-cs', '--config=' . self::VALID_CONFIG, '--fix', $filePath],
                $this->stdout,
                $this->stderr,
            );

            self::assertSame(0, $app->run());
            self::assertSame(
                '<root><exceptionname>RuntimeException</exceptionname></root>',
                file_get_contents($filePath),
            );
        } finally {
            @unlink($filePath);
        }
    }

    /** @param resource $stream */
    private function readStream(mixed $stream): string
    {
        rewind($stream);

        return stream_get_contents($stream) ?: '';
    }
}
