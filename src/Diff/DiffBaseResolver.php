<?php

declare(strict_types=1);

namespace DocbookCS\Diff;

use DocbookCS\Git\GitException;
use DocbookCS\Git\GitClient;

final readonly class DiffBaseResolver
{
    private const string DEFAULT_BRANCH = 'master';

    private const string DEFAULT_BRANCH_REFERENCE = 'refs/heads/master';

    private const string PHP_DOCS_REPO_PATTERN = '/^doc-(?:base|[a-z]{2}(?:_[a-z]{2})?)$/i';

    public function __construct(
        private GitClient $git,
        private UpstreamResolver $officialUpstream,
    ) {
    }

    /** @throws GitException */
    public function resolve(string $repoRoot): string
    {
        $repoName = $this->repositoryName($repoRoot);

        if ($repoName === null) {
            return $this->localMergeBase($repoRoot);
        }

        return $this->officialUpstream->resolve($repoRoot, $repoName)
            ?? $this->localMergeBase($repoRoot);
    }

    /** @throws GitException */
    private function localMergeBase(string $repoRoot): string
    {
        $baseReference = $this->localBaseReference($repoRoot);

        if (null === $mergeBase = $this->git->findMergeBase($repoRoot, 'HEAD', $baseReference)) {
            throw GitException::mergeBaseNotFound($baseReference);
        }

        return $mergeBase;
    }

    /** @throws GitException */
    private function localBaseReference(string $repoRoot): string
    {
        if ($this->git->currentBranchName($repoRoot) === self::DEFAULT_BRANCH) {
            return $this->git->configuredUpstreamReference($repoRoot, self::DEFAULT_BRANCH) ?? 'HEAD';
        }

        if ($this->git->resolveCommitHash($repoRoot, self::DEFAULT_BRANCH_REFERENCE) !== null) {
            return self::DEFAULT_BRANCH_REFERENCE;
        }

        throw GitException::localMasterNotFound();
    }

    /** @throws GitException */
    private function repositoryName(string $repoRoot): ?string
    {
        $repoNames = [];

        foreach ($this->git->configuredRemoteUrls($repoRoot) as $url) {
            $repoName = $this->repositoryNameFrom($url);

            if ($repoName !== null) {
                $repoNames[$repoName] = true;
            }
        }

        if (count($repoNames) === 1) {
            return array_key_first($repoNames);
        }

        return $repoNames === []
            ? $this->repositoryNameFrom($repoRoot)
            : null;
    }

    private function repositoryNameFrom(string $path): ?string
    {
        $path = rtrim(str_replace(['\\', ':'], '/', $path), '/');
        $name = preg_replace('/\\.git$/i', '', basename($path));

        $isPhpDocsRepo = is_string($name)
            && preg_match(self::PHP_DOCS_REPO_PATTERN, $name) === 1;

        return $isPhpDocsRepo ? strtolower($name) : null;
    }
}
