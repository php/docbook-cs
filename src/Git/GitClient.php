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
     * @return list<string>
     * @throws GitException
     */
    public function remoteUrlsFromLocalConfiguration(string $repoRoot): array
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

    /** @throws GitException */
    public function currentBranchName(string $repoRoot): ?string
    {
        $result = $this->execute(
            ['git', 'symbolic-ref', '--quiet', '--short', 'HEAD'],
            $repoRoot,
        );

        // null only when HEAD is detached
        return $result->exitCode === 0 ? trim($result->stdout) : null;
    }

    /** @throws GitException */
    public function upstreamReferenceFromLocalConfiguration(string $repoRoot, string $branch): ?string
    {
        $result = $this->execute(
            ['git', 'rev-parse', '--abbrev-ref', '--symbolic-full-name', $branch . '@{upstream}'],
            $repoRoot,
        );

        return $result->exitCode === 0 ? trim($result->stdout) : null;
    }

    /** @throws GitException */
    public function resolveCommitHash(string $repoRoot, string $reference): ?string
    {
        $result = $this->execute(
            ['git', 'rev-parse', '--verify', '--quiet', $reference . '^{commit}'],
            $repoRoot,
        );

        return $result->exitCode === 0 ? trim($result->stdout) : null;
    }

    /** @throws GitException */
    public function findMergeBase(
        string $repoRoot,
        string $firstReference,
        string $secondReference,
        ?string $alternateObjectDirectory = null,
    ): ?string {
        $environment = $alternateObjectDirectory !== null
            ? ['GIT_ALTERNATE_OBJECT_DIRECTORIES' => $alternateObjectDirectory]
            : [];

        // finds merge base using optional external object directory
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

    /** @throws GitException */
    public function isBareRepo(string $repoPath): bool
    {
        $result = $this->execute(
            ['git', '-C', $repoPath, 'rev-parse', '--is-bare-repository'],
            dirname($repoPath),
        );

        return $result->exitCode === 0 && trim($result->stdout) === 'true';
    }

    /** @throws GitException */
    public function initialiseBareRepoForCache(string $repoPath): bool
    {
        return $this->execute(
            ['git', 'init', '--bare', '--quiet', $repoPath],
            dirname($repoPath),
        )->exitCode === 0;
    }

    /** @throws GitException */
    public function fetchToCacheRepo(string $repoPath, string $url, string $branch, string $reference): ProcessResult
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
