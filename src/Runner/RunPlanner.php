<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Config\ConfigData;
use DocbookCS\Diff\Diff;
use DocbookCS\Diff\DiffParser;
use DocbookCS\Diff\DiffProviderInterface;
use DocbookCS\Diff\GitDiffProvider;
use DocbookCS\Path\EntityResolver;

final readonly class RunPlanner
{
    private EntityResolver $entityResolver;

    private DiffProviderInterface $diffProvider;

    public function __construct(
        private ConfigData $config,
        private RunMode $mode = RunMode::Sniff,
        private bool $wide = false,
        ?DiffProviderInterface $diffProvider = null,
    ) {
        $this->diffProvider = $diffProvider ?? new GitDiffProvider();

        $this->entityResolver = new EntityResolver(
            $config->getProjectRoots(),
            $config->getEntityPaths(),
        );
    }

    /**
     * @param list<string> $paths
     * @throws \InvalidArgumentException if paths and a piped diff are both provided.
     * @throws \RuntimeException if the contribution diff cannot be determined.
     * @throws \UnexpectedValueException if an entity or selected directory cannot be read.
     */
    public function plan(array $paths, ?string $pipedDiff): RunPlan
    {
        if ($paths === []) {
            return $this->planDiff(new DiffParser()->parse($pipedDiff ?? $this->diffProvider->for(getcwd() ?: '.')));
        }

        if ($pipedDiff !== null) {
            throw new \InvalidArgumentException('Paths cannot be combined with diff input.');
        }

        return $this->planPaths($paths);
    }

    /**
     * @param list<string> $paths
     * @throws \UnexpectedValueException if an entity or selected directory cannot be read.
     */
    public function planPaths(array $paths): RunPlan
    {
        return new RunPlan(
            mode: $this->mode,
            sniffs: $this->config->getSniffs(),
            targets: $this->scopeResolver()->resolvePaths($paths),
            entities: $this->entityResolver->resolve(),
        );
    }

    /** @throws \UnexpectedValueException if an entity directory cannot be read. */
    public function planDiff(Diff $diff): RunPlan
    {
        return new RunPlan(
            mode: $this->mode,
            sniffs: $this->config->getSniffs(),
            targets: $this->scopeResolver()->resolveDiff($diff),
            entities: $this->entityResolver->resolve(),
        );
    }

    /** @throws \UnexpectedValueException if an entity directory cannot be read. */
    private function scopeResolver(): RunScopeResolver
    {
        return new RunScopeResolver(
            $this->config,
            $this->entityResolver->paths(),
            $this->wide,
        );
    }
}
