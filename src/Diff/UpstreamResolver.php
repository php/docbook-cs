<?php

declare(strict_types=1);

namespace DocbookCS\Diff;

use DocbookCS\Git\GitClient;
use DocbookCS\Git\GitException;

final readonly class UpstreamResolver
{
    private const string OFFICIAL_REPOSITORY = 'php/%s';

    private const string OFFICIAL_REPOSITORY_URL = 'https://github.com/' . self::OFFICIAL_REPOSITORY . '.git';

    private const string OFFICIAL_BRANCH = 'master';

    private const string CACHE_REPOSITORY_PATH = '%s/%s.git';

    private const string CACHE_OBJECTS_PATH = '%s/objects';

    private const string CACHE_LOCK_PATH = '%s/%s.lock';

    private const string INVALID_CACHE_REPOSITORY_PATH = '%s.invalid-%s';

    private const string CACHED_UPSTREAM_REFERENCE = 'refs/docbook-cs/upstream';

    private string $cacheDirectory;

    public function __construct(
        private GitClient $git,
        string $cacheDirectory,
    ) {
        $this->cacheDirectory = rtrim($cacheDirectory, '/\\');
    }

    /** Serialises cache updates across parallel runs. */
    public function resolve(string $repoRoot, string $repoName): ?string
    {
        if (!$this->prepareCacheDirectory()) {
            return null;
        }

        $lock = @fopen($this->cacheLockPath($repoName), 'c');

        if ($lock === false) {
            return null;
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                return null;
            }

            return $this->refreshAndResolve($repoRoot, $repoName);
        } catch (GitException) {
            return null;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @throws GitException */
    private function refreshAndResolve(string $repoRoot, string $repoName): ?string
    {
        $cacheRepository = $this->cacheRepositoryPath($repoName);

        if (!$this->prepareCacheRepository($cacheRepository)) {
            return null;
        }

        $this->git->fetchBranchIntoRepo(
            $cacheRepository,
            sprintf(self::OFFICIAL_REPOSITORY_URL, $repoName),
            self::OFFICIAL_BRANCH,
            self::CACHED_UPSTREAM_REFERENCE,
        );

        $upstreamCommit = $this->git->resolveCommitHash(
            $cacheRepository,
            self::CACHED_UPSTREAM_REFERENCE,
        );

        if ($upstreamCommit === null) {
            return null;
        }

        return $this->git->findMergeBase(
            $repoRoot,
            'HEAD',
            $upstreamCommit,
            sprintf(self::CACHE_OBJECTS_PATH, $cacheRepository),
        );
    }

    private function prepareCacheDirectory(): bool
    {
        return is_dir($this->cacheDirectory)
            || @mkdir($this->cacheDirectory, 0777, recursive: true)
            || is_dir($this->cacheDirectory);
    }

    /** @throws GitException */
    private function prepareCacheRepository(string $cacheRepository): bool
    {
        if (!is_dir($cacheRepository)) {
            return $this->git->initialiseBareRepo($cacheRepository);
        }

        if ($this->git->isBareRepo($cacheRepository)) {
            return true;
        }

        // Keep unexpected directories instead of deleting user data.
        $invalidRepository = sprintf(self::INVALID_CACHE_REPOSITORY_PATH, $cacheRepository, date('YmdHis'));

        return @rename($cacheRepository, $invalidRepository)
            && $this->git->initialiseBareRepo($cacheRepository);
    }

    private function cacheRepositoryPath(string $repoName): string
    {
        return sprintf(self::CACHE_REPOSITORY_PATH, $this->cacheDirectory, $repoName);
    }

    private function cacheLockPath(string $repoName): string
    {
        return sprintf(self::CACHE_LOCK_PATH, $this->cacheDirectory, $repoName);
    }
}
