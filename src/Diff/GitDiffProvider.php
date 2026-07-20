<?php

declare(strict_types=1);

namespace DocbookCS\Diff;

use DocbookCS\Process\NativeProcessRunner;
use DocbookCS\Process\ProcessRunnerInterface;

final readonly class GitDiffProvider implements DiffProviderInterface
{
    public function __construct(
        private ProcessRunnerInterface $processRunner = new NativeProcessRunner(),
    ) {
    }

    /** @throws \RuntimeException if the repository, branch point, or diff cannot be determined. */
    public function for(string $workingDirectory): string
    {
        $repositoryRoot = trim($this->runOrThrow(
            ['git', 'rev-parse', '--show-toplevel'],
            $workingDirectory,
            'Could not find Git repository.',
        ));

        $baseReference = $this->resolveBaseReference($repositoryRoot);
        $mergeBase = $this->runOrThrow(
            ['git', 'merge-base', 'HEAD', $baseReference],
            $repositoryRoot,
            sprintf('Unclear where HEAD branched from %s.', $baseReference),
        );

        return $this->runOrThrow(
            ['git', 'diff', '--no-ext-diff', '--no-color', trim($mergeBase), '--'],
            $repositoryRoot,
            'Could not read diff.',
        );
    }

    /** @throws \RuntimeException if no default branch reference exists. */
    private function resolveBaseReference(string $repositoryRoot): string
    {
        $candidates = [];

        foreach (['upstream', 'origin'] as $remote) {
            $result = $this->processRunner->run(
                ['git', 'symbolic-ref', '--quiet', sprintf('refs/remotes/%s/HEAD', $remote)],
                $repositoryRoot,
            );

            if ($result->exitCode === 0) {
                $candidates[] = trim($result->stdout);
            }

            $candidates[] = sprintf('refs/remotes/%s/main', $remote);
            $candidates[] = sprintf('refs/remotes/%s/master', $remote);
        }

        $candidates[] = 'refs/heads/main';
        $candidates[] = 'refs/heads/master';

        foreach (array_unique($candidates) as $candidate) {
            $result = $this->processRunner->run(
                ['git', 'rev-parse', '--verify', '--quiet', $candidate . '^{commit}'],
                $repositoryRoot,
            );

            if ($result->exitCode === 0) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not determine the upstream default branch for the contribution diff.');
    }

    /**
     * @param list<string> $command
     * @throws \RuntimeException if the command fails.
     */
    private function runOrThrow(array $command, string $workingDirectory, string $error): string
    {
        $result = $this->processRunner->run($command, $workingDirectory);

        if ($result->exitCode === 0) {
            return $result->stdout;
        }

        $detail = trim($result->stderr);

        throw new \RuntimeException($detail !== '' ? "$error $detail" : $error);
    }
}
