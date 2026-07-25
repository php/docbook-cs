<?php

declare(strict_types=1);

namespace DocbookCS\Process;

interface ProcessRunnerInterface
{
    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     * @throws ProcessException if the process cannot be started.
     */
    public function run(array $command, string $workingDirectory, array $environment = []): ProcessResult;
}
