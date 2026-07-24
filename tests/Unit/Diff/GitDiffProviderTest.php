<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Diff;

use DocbookCS\Diff\DiffBaseResolver;
use DocbookCS\Diff\GitDiffProvider;
use DocbookCS\Diff\UpstreamResolver;
use DocbookCS\Git\GitClient;
use DocbookCS\Git\GitException;
use DocbookCS\Process\NativeProcessRunner;
use DocbookCS\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(DiffBaseResolver::class),
    CoversClass(GitClient::class),
    CoversClass(GitDiffProvider::class),
    CoversClass(GitException::class),
    CoversClass(UpstreamResolver::class),
    //
    UsesClass(NativeProcessRunner::class),
    UsesClass(ProcessResult::class),
]
final class GitDiffProviderTest extends TestCase
{
    private string $workspace;
    private string $repository;
    private string $cacheDirectory;
    private NativeProcessRunner $processRunner;
    private string|false $gitConfigGlobal;

    protected function setUp(): void
    {
        $this->processRunner = new NativeProcessRunner();
        $this->workspace = sys_get_temp_dir() . '/docbook-cs-git-diff-' . bin2hex(random_bytes(6));
        $this->repository = $this->workspace . '/en';
        $this->cacheDirectory = $this->workspace . '/cache';
        $this->gitConfigGlobal = getenv('GIT_CONFIG_GLOBAL');

        mkdir($this->workspace);
        mkdir($this->repository);
        putenv('GIT_CONFIG_GLOBAL=' . $this->workspace . '/gitconfig');
    }

    protected function tearDown(): void
    {
        putenv($this->gitConfigGlobal === false
            ? 'GIT_CONFIG_GLOBAL'
            : 'GIT_CONFIG_GLOBAL=' . $this->gitConfigGlobal);

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
    public function itDiffsTheWorkingTreeFromTheCanonicalUpstreamBranchPoint(): void
    {
        $this->initializeRepository();
        file_put_contents($this->repository . '/base.xml', "base\n");
        $this->git('add', 'base.xml');
        $this->git('commit', '--quiet', '-m', 'Base');

        $officialRepository = $this->createOfficialRepository();
        $this->redirectCanonicalUrl($officialRepository);

        $this->git('switch', '--quiet', '-c', 'contribution');
        $this->git('branch', '--delete', '--force', 'master');

        file_put_contents($this->repository . '/committed.xml', "committed\n");
        $this->git('add', 'committed.xml');
        $this->git('commit', '--quiet', '-m', 'Contribution');
        file_put_contents($this->repository . '/base.xml', "working tree\n");

        $diff = $this->provider()->for($this->repository);

        self::assertStringContainsString('diff --git a/base.xml b/base.xml', $diff);
        self::assertStringContainsString('+working tree', $diff);
        self::assertStringContainsString('diff --git a/committed.xml b/committed.xml', $diff);
        self::assertStringContainsString('+committed', $diff);
        self::assertDirectoryExists($this->cacheDirectory . '/doc-en.git');
    }

    #[Test]
    public function itIgnoresStaleLocalRemoteTrackingReferences(): void
    {
        $this->initializeRepository();
        file_put_contents($this->repository . '/base.xml', "base\n");
        $this->git('add', 'base.xml');
        $this->git('commit', '--quiet', '-m', 'Base');
        $base = $this->git('rev-parse', 'HEAD');

        file_put_contents($this->repository . '/upstream.xml', "upstream\n");
        $this->git('add', 'upstream.xml');
        $this->git('commit', '--quiet', '-m', 'Upstream');
        $upstream = $this->git('rev-parse', 'HEAD');

        $officialRepository = $this->createOfficialRepository();
        $this->redirectCanonicalUrl($officialRepository);

        $this->git('update-ref', 'refs/remotes/upstream/master', $base);
        $this->git('symbolic-ref', 'refs/remotes/upstream/HEAD', 'refs/remotes/upstream/master');
        $this->git('update-ref', 'refs/remotes/origin/master', $upstream);
        $this->git('switch', '--quiet', '-c', 'contribution');

        file_put_contents($this->repository . '/contribution.xml', "contribution\n");
        $this->git('add', 'contribution.xml');
        $this->git('commit', '--quiet', '-m', 'Contribution');

        $diff = $this->provider()->for($this->repository);

        self::assertStringContainsString('diff --git a/contribution.xml b/contribution.xml', $diff);
        self::assertStringNotContainsString('diff --git a/upstream.xml b/upstream.xml', $diff);
    }

    #[Test]
    public function itUsesTheLastCanonicalCacheWhenRefreshingFails(): void
    {
        $this->initializeRepository();
        file_put_contents($this->repository . '/base.xml', "base\n");
        $this->git('add', 'base.xml');
        $this->git('commit', '--quiet', '-m', 'Base');

        $officialRepository = $this->createOfficialRepository();
        $this->redirectCanonicalUrl($officialRepository);

        $this->git('switch', '--quiet', '-c', 'contribution');
        file_put_contents($this->repository . '/contribution.xml', "contribution\n");
        $this->git('add', 'contribution.xml');

        $provider = $this->provider();
        $provider->for($this->repository);

        rename($officialRepository, $officialRepository . '.offline');

        $diff = $provider->for($this->repository);

        self::assertStringContainsString('diff --git a/contribution.xml b/contribution.xml', $diff);
    }

    #[Test]
    public function itRebuildsAnInvalidCacheRepository(): void
    {
        $this->initializeRepository();
        file_put_contents($this->repository . '/base.xml', "base\n");
        $this->git('add', 'base.xml');
        $this->git('commit', '--quiet', '-m', 'Base');

        $officialRepository = $this->createOfficialRepository();
        $this->redirectCanonicalUrl($officialRepository);
        mkdir($this->cacheDirectory);
        mkdir($this->cacheDirectory . '/doc-en.git');
        file_put_contents($this->cacheDirectory . '/doc-en.git/unexpected', '');

        $this->git('switch', '--quiet', '-c', 'contribution');
        file_put_contents($this->repository . '/contribution.xml', "contribution\n");
        $this->git('add', 'contribution.xml');

        $diff = $this->provider()->for($this->repository);

        self::assertStringContainsString(
            'diff --git a/contribution.xml b/contribution.xml',
            $diff,
        );
        self::assertCount(
            1,
            glob($this->cacheDirectory . '/doc-en.git.invalid-*') ?: [],
        );
    }

    #[Test]
    public function itFallsBackToLocalMasterWhenCanonicalRepositoryCannotBeIdentified(): void
    {
        $this->repository = $this->workspace . '/project';
        mkdir($this->repository);
        $this->initializeRepository();

        file_put_contents($this->repository . '/base.xml', "base\n");
        $this->git('add', 'base.xml');
        $this->git('commit', '--quiet', '-m', 'Base');
        $this->git('switch', '--quiet', '-c', 'contribution');

        file_put_contents($this->repository . '/contribution.xml', "contribution\n");
        $this->git('add', 'contribution.xml');

        $diff = $this->provider()->for($this->repository);

        self::assertStringContainsString('diff --git a/contribution.xml b/contribution.xml', $diff);
    }

    #[Test]
    public function itFindsUnpushedCommitsOnLocalMasterFromItsConfiguredUpstream(): void
    {
        $this->repository = $this->workspace . '/project';
        mkdir($this->repository);
        $this->initializeRepository();

        file_put_contents($this->repository . '/base.xml', "base\n");
        $this->git('add', 'base.xml');
        $this->git('commit', '--quiet', '-m', 'Base');

        $upstreamRepository = $this->workspace . '/upstream.git';
        $this->runCommand(
            ['git', 'clone', '--bare', '--quiet', $this->repository, $upstreamRepository],
            $this->workspace,
        );
        $this->git('remote', 'add', 'origin', $upstreamRepository);
        $this->git('fetch', '--quiet', 'origin');
        $this->git('branch', '--set-upstream-to=origin/master', 'master');

        file_put_contents($this->repository . '/unpushed.xml', "unpushed\n");
        $this->git('add', 'unpushed.xml');
        $this->git('commit', '--quiet', '-m', 'Unpushed');

        $diff = $this->provider()->for($this->repository);

        self::assertStringContainsString('diff --git a/unpushed.xml b/unpushed.xml', $diff);
    }

    #[Test]
    public function itFallsBackToLocalMasterWhenNoCanonicalCacheCanBeFetched(): void
    {
        $this->initializeRepository();
        file_put_contents($this->repository . '/base.xml', "base\n");
        $this->git('add', 'base.xml');
        $this->git('commit', '--quiet', '-m', 'Base');
        $this->git('remote', 'add', 'origin', $this->workspace . '/missing/doc-en.git');
        $this->redirectCanonicalUrl($this->workspace . '/missing/doc-en.git');

        $this->git('switch', '--quiet', '-c', 'contribution');
        file_put_contents($this->repository . '/contribution.xml', "contribution\n");
        $this->git('add', 'contribution.xml');

        $diff = $this->provider()->for($this->repository);

        self::assertStringContainsString('diff --git a/contribution.xml b/contribution.xml', $diff);
    }

    #[Test]
    public function itFailsClearlyWhenNoCanonicalOrLocalMasterCanBeFound(): void
    {
        $this->repository = $this->workspace . '/project';
        mkdir($this->repository);
        $this->initializeRepository('contribution');

        file_put_contents($this->repository . '/base.xml', "base\n");
        $this->git('add', 'base.xml');
        $this->git('commit', '--quiet', '-m', 'Contribution');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIs('Could not find local master branch for the contribution diff.');

        $this->provider()->for($this->repository);
    }

    #[Test]
    public function itFailsClearlyWhenLocalHistoriesAreUnrelated(): void
    {
        $this->repository = $this->workspace . '/project';
        mkdir($this->repository);
        $this->initializeRepository();

        file_put_contents($this->repository . '/base.xml', "base\n");
        $this->git('add', 'base.xml');
        $this->git('commit', '--quiet', '-m', 'Base');
        $this->git('switch', '--orphan', 'contribution');
        file_put_contents($this->repository . '/contribution.xml', "contribution\n");
        $this->git('add', '--all');
        $this->git('commit', '--quiet', '-m', 'Contribution');

        $this->expectException(GitException::class);
        $this->expectExceptionMessageIs(
            'Unclear where HEAD branched from refs/heads/master.'
        );

        $this->provider()->for($this->repository);
    }

    #[Test]
    public function itIncludesGitErrorsWhenACommandFails(): void
    {
        $this->repository = $this->workspace . '/not-a-repository';
        mkdir($this->repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Could not find Git repository. fatal: not a git repository');

        $this->provider()->for($this->repository);
    }

    private function initializeRepository(string $branch = 'master'): void
    {
        $this->git('init', '--quiet', '--initial-branch=' . $branch);
        $this->git('config', 'user.name', 'DocbookCS Tests');
        $this->git('config', 'user.email', 'docbook-cs@example.invalid');
    }

    private function createOfficialRepository(): string
    {
        $officialRepository = $this->workspace . '/doc-en.git';
        $this->runCommand(
            ['git', 'clone', '--bare', '--quiet', $this->repository, $officialRepository],
            $this->workspace,
        );
        $this->git('remote', 'add', 'origin', $officialRepository);

        return $officialRepository;
    }

    private function redirectCanonicalUrl(string $officialRepository): void
    {
        $this->runCommand(
            [
                'git',
                'config',
                '--file',
                $this->workspace . '/gitconfig',
                'url.' . $officialRepository . '.insteadOf',
                'https://github.com/php/doc-en.git',
            ],
            $this->workspace,
        );
    }

    private function provider(): GitDiffProvider
    {
        return new GitDiffProvider($this->processRunner, $this->cacheDirectory);
    }

    private function git(string ...$arguments): string
    {
        return $this->runCommand(array_values(['git', ...$arguments]), $this->repository);
    }

    /** @param list<string> $command */
    private function runCommand(array $command, string $workingDirectory): string
    {
        $result = $this->processRunner->run($command, $workingDirectory);

        self::assertSame(0, $result->exitCode, $result->stderr);

        return trim($result->stdout);
    }
}
