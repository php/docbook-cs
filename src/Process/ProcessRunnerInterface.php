<?php

declare(strict_types=1);

namespace DocbookCS\Process;

interface ProcessRunnerInterface
{
    /**
     * @param list<string> $command
     * @throws \RuntimeException if the process cannot be started.
     */
    public function run(array $command, string $workingDirectory): ProcessResult;
}
