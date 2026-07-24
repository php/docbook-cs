<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Diff;

use DocbookCS\Diff\GitDiffProvider;
use DocbookCS\Process\NativeProcessRunner;
use DocbookCS\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(GitDiffProvider::class),
    //
    UsesClass(NativeProcessRunner::class),
    UsesClass(ProcessResult::class),
]
final class GitDiffProviderTest extends TestCase
{
    private string $repository;
    private NativeProcessRunner $processRunner;

    protected function setUp(): void
    {
        $this->processRunner = new NativeProcessRunner();

        $tmpDir = sys_get_temp_dir() . '/docbook-cs-git-diff-' . bin2hex(random_bytes(6));
        mkdir($tmpDir);
        $this->repository = $tmpDir;
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->repository, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo) {
                throw new \UnexpectedValueException('Unexpected directory entry.');
            }

            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->repository);
    }

    #[Test]
    public function itDiffsTheWorkingTreeFromTheUpstreamBranchPoint(): void
    {
        $this->git('init', '--quiet', '--initial-branch=main');
        $this->configureAuthor();

        file_put_contents($this->repository . '/base.xml', "base\n");
        $this->git('add', 'base.xml');
        $this->git('commit', '--quiet', '-m', 'Base');

        $base = $this->git('rev-parse', 'HEAD');
        $this->git('update-ref', 'refs/remotes/upstream/main', $base);
        $this->git('symbolic-ref', 'refs/remotes/upstream/HEAD', 'refs/remotes/upstream/main');
        $this->git('switch', '--quiet', '-c', 'contribution');
        $this->git('branch', '--delete', '--force', 'main');

        file_put_contents($this->repository . '/committed.xml', "committed\n");
        $this->git('add', 'committed.xml');
        $this->git('commit', '--quiet', '-m', 'Contribution');
        file_put_contents($this->repository . '/base.xml', "working tree\n");

        $diff = new GitDiffProvider($this->processRunner)->for($this->repository);

        self::assertStringContainsString('diff --git a/base.xml b/base.xml', $diff);
        self::assertStringContainsString('+working tree', $diff);
        self::assertStringContainsString('diff --git a/committed.xml b/committed.xml', $diff);
        self::assertStringContainsString('+committed', $diff);
    }

    #[Test]
    public function itFailsClearlyWhenNoUpstreamDefaultBranchCanBeFound(): void
    {
        $this->git('init', '--quiet', '--initial-branch=contribution');
        $this->configureAuthor();

        file_put_contents($this->repository . '/base.xml', "base\n");
        $this->git('add', 'base.xml');
        $this->git('commit', '--quiet', '-m', 'Contribution');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Could not determine the upstream default branch');

        new GitDiffProvider($this->processRunner)->for($this->repository);
    }

    #[Test]
    public function itIncludesGitErrorsWhenACommandFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Could not find Git repository. fatal: not a git repository');

        new GitDiffProvider($this->processRunner)->for($this->repository);
    }

    private function configureAuthor(): void
    {
        $this->git('config', 'user.name', 'DocbookCS Tests');
        $this->git('config', 'user.email', 'docbook-cs@example.invalid');
    }

    private function git(string ...$arguments): string
    {
        $result = $this->processRunner->run(array_values(['git', ...$arguments]), $this->repository);

        self::assertSame(0, $result->exitCode, $result->stderr);

        return trim($result->stdout);
    }
}
