<?php

declare(strict_types=1);

namespace DocbookCS\Diff;

use DocbookCS\Git\GitClient;
use DocbookCS\Git\GitException;
use DocbookCS\Process\NativeProcessRunner;
use DocbookCS\Process\ProcessRunnerInterface;

final readonly class GitDiffProvider implements DiffProviderInterface
{
    private GitClient $git;

    private DiffBaseResolver $baseResolver;

    public function __construct(
        ProcessRunnerInterface $processRunner = new NativeProcessRunner(),
        ?string $cacheDirectory = null,
    ) {
        $gitClient =  new GitClient($processRunner);

        $cacheDirectory ??= dirname(__DIR__, 2) . '/var/upstream';

        $this->baseResolver = new DiffBaseResolver(
            $gitClient,
            new UpstreamResolver($gitClient, $cacheDirectory),
        );

        $this->git = $gitClient;
    }

    /** @throws GitException */
    public function for(string $workingDirectory): string
    {
        $mergeBase = $this->baseResolver->resolve(
            $repoRoot = $this->git->repoRoot($workingDirectory)
        );

        return $this->git->diffFromMergeBase($repoRoot, $mergeBase);
    }
}
