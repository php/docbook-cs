<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Diff;

use DocbookCS\Diff\UpstreamResolver;
use DocbookCS\Git\GitClient;
use DocbookCS\Git\GitException;
use DocbookCS\Process\ProcessException;
use DocbookCS\Process\ProcessResult;
use DocbookCS\Process\ProcessRunnerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(UpstreamResolver::class),
    //
    UsesClass(GitClient::class),
    UsesClass(GitException::class),
    UsesClass(ProcessException::class),
    UsesClass(ProcessResult::class),
]
final class UpstreamResolverTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/docbook-cs-upstream-' . bin2hex(random_bytes(6));
        mkdir($this->workspace);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->workspace, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo) {
                throw new \UnexpectedValueException('Unexpected directory entry.');
            }

            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->workspace);
    }

    #[Test]
    public function itFallsBackWhenTheCacheDirectoryCannotBeCreated(): void
    {
        $cachePath = $this->workspace . '/cache';
        file_put_contents($cachePath, 'not a directory');

        self::assertNull($this->resolver($this->successfulRunner(), $cachePath)->resolve('/repo', 'doc-en'));
    }

    #[Test]
    public function itFallsBackWhenTheCacheLockCannotBeOpened(): void
    {
        $cachePath = $this->workspace . '/cache';
        mkdir($cachePath);
        mkdir($cachePath . '/doc-en.lock');

        self::assertNull($this->resolver($this->successfulRunner(), $cachePath)->resolve('/repo', 'doc-en'));
    }

    #[Test]
    public function itFallsBackWhenTheCacheRepositoryCannotBeInitialised(): void
    {
        $runner = new class implements ProcessRunnerInterface {
            public function run(array $command, string $workingDirectory, array $environment = []): ProcessResult
            {
                return new ProcessResult(1, '', 'Could not initialise cache.');
            }
        };

        self::assertNull($this->resolver($runner)->resolve('/repo', 'doc-en'));
    }

    #[Test]
    public function itFallsBackWhenGitCannotBeStarted(): void
    {
        $runner = new class implements ProcessRunnerInterface {
            public function run(array $command, string $workingDirectory, array $environment = []): ProcessResult
            {
                throw ProcessException::couldNotStart();
            }
        };

        self::assertNull($this->resolver($runner)->resolve('/repo', 'doc-en'));
    }

    private function resolver(ProcessRunnerInterface $runner, ?string $cachePath = null): UpstreamResolver
    {
        return new UpstreamResolver(
            new GitClient($runner),
            $cachePath ?? $this->workspace . '/cache',
        );
    }

    private function successfulRunner(): ProcessRunnerInterface
    {
        return new class implements ProcessRunnerInterface {
            public function run(array $command, string $workingDirectory, array $environment = []): ProcessResult
            {
                return new ProcessResult(0, '', '');
            }
        };
    }
}
