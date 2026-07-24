<?php

declare(strict_types=1);

namespace DocbookCS\Git;

use DocbookCS\Process\ProcessException;
use DocbookCS\Process\ProcessResult;
use DocbookCS\Process\ProcessRunnerInterface;

final readonly class GitClient
{
    private const string FORCED_BRANCH_REF_SPEC = '+refs/heads/%s:%s';

    public function __construct(private ProcessRunnerInterface $processRunner)
    {
    }

    /** @throws GitException */
    public function repoRoot(string $workingDirectory): string
    {
        return trim($this->runAndRequireSuccess(
            ['git', 'rev-parse', '--show-toplevel'],
            $workingDirectory,
            'Could not find Git repository.',
        ));
    }

    /**
     * Reads configured remote URLs without contacting any remote.
     *
     * @return list<string>
     * @throws GitException
     */
    public function configuredRemoteUrls(string $repoRoot): array
    {
        $result = $this->execute(
            ['git', 'config', '--get-regexp', '^remote\\..*\\.url$'],
            $repoRoot,
        );

        if ($result->exitCode !== 0) {
            return [];
        }

        $urls = [];

        foreach (preg_split('/\\R/', trim($result->stdout)) ?: [] as $line) {
            $parts = preg_split('/\\s+/', $line, 2);

            if (isset($parts[1])) {
                $urls[] = $parts[1];
            }
        }

        return $urls;
    }

    /**
     * Returns the branch name, or null for a detached HEAD.
     *
     * @throws GitException
     */
    public function currentBranchName(string $repoRoot): ?string
    {
        $result = $this->execute(
            ['git', 'symbolic-ref', '--quiet', '--short', 'HEAD'],
            $repoRoot,
        );

        return $result->exitCode === 0 ? trim($result->stdout) : null;
    }

    /**
     * Returns the tracking reference selected by Git configuration.
     *
     * @throws GitException
     */
    public function configuredUpstreamReference(string $repoRoot, string $branch): ?string
    {
        $result = $this->execute(
            ['git', 'rev-parse', '--abbrev-ref', '--symbolic-full-name', $branch . '@{upstream}'],
            $repoRoot,
        );

        return $result->exitCode === 0 ? trim($result->stdout) : null;
    }

    /**
     * Resolves a reference to its commit hash.
     *
     * @throws GitException
     */
    public function resolveCommitHash(string $repoRoot, string $reference): ?string
    {
        $result = $this->execute(
            ['git', 'rev-parse', '--verify', '--quiet', $reference . '^{commit}'],
            $repoRoot,
        );

        return $result->exitCode === 0 ? trim($result->stdout) : null;
    }

    /**
     * Finds a merge base using an optional external object directory.
     *
     * @throws GitException
     */
    public function findMergeBase(
        string $repoRoot,
        string $firstReference,
        string $secondReference,
        ?string $alternateObjectDirectory = null,
    ): ?string {
        $environment = $alternateObjectDirectory !== null
            ? ['GIT_ALTERNATE_OBJECT_DIRECTORIES' => $alternateObjectDirectory]
            : [];

        $result = $this->execute(
            ['git', 'merge-base', $firstReference, $secondReference],
            $repoRoot,
            $environment,
        );

        return $result->exitCode === 0 ? trim($result->stdout) : null;
    }

    /** @throws GitException */
    public function diffFromMergeBase(string $repoRoot, string $mergeBase): string
    {
        return $this->runAndRequireSuccess(
            ['git', 'diff', '--no-ext-diff', '--no-color', $mergeBase, '--'],
            $repoRoot,
            'Could not read diff.',
        );
    }

    /**
     * Checks whether a path contains a bare Git repository.
     *
     * @throws GitException
     */
    public function isBareRepo(string $repoPath): bool
    {
        $result = $this->execute(
            ['git', '-C', $repoPath, 'rev-parse', '--is-bare-repository'],
            dirname($repoPath),
        );

        return $result->exitCode === 0 && trim($result->stdout) === 'true';
    }

    /**
     * Creates a bare repository for cached upstream history.
     *
     * @throws GitException
     */
    public function initialiseBareRepo(string $repoPath): bool
    {
        return $this->execute(
            ['git', 'init', '--bare', '--quiet', $repoPath],
            dirname($repoPath),
        )->exitCode === 0;
    }

    /**
     * Fetches a branch into one private reference.
     * Leaves the actual repository unchanged.
     *
     * @throws GitException
     */
    public function fetchBranchIntoRepo(string $repoPath, string $url, string $branch, string $reference): ProcessResult
    {
        return $this->execute(
            [
                'git',
                '-c',
                'credential.interactive=false',
                '-c',
                'http.lowSpeedLimit=1',
                '-c',
                'http.lowSpeedTime=10',
                '-C',
                $repoPath,
                'fetch',
                '--quiet',
                '--no-tags',
                '--filter=tree:0',
                $url,
                sprintf(self::FORCED_BRANCH_REF_SPEC, $branch, $reference),
            ],
            dirname($repoPath),
            ['GIT_TERMINAL_PROMPT' => '0'],
        );
    }

    /**
     * @param list<string> $command
     * @throws GitException
     */
    private function runAndRequireSuccess(array $command, string $workingDirectory, string $error): string
    {
        $result = $this->execute($command, $workingDirectory);

        if ($result->exitCode === 0) {
            return $result->stdout;
        }

        $detail = trim($result->stderr);

        throw GitException::commandFailed($error, $detail);
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     * @throws GitException
     */
    private function execute(array $command, string $workingDirectory, array $environment = []): ProcessResult
    {
        try {
            return $this->processRunner->run($command, $workingDirectory, $environment);
        } catch (ProcessException $exception) {
            throw GitException::processFailed($exception);
        }
    }
}
