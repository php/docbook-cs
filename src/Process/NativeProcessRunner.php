<?php

declare(strict_types=1);

namespace DocbookCS\Process;

final class NativeProcessRunner implements ProcessRunnerInterface
{
    public function run(array $command, string $workingDirectory, array $environment = []): ProcessResult
    {
        $process = proc_open(
            $command,
            [
                ['pipe', 'r'],
                ['pipe', 'w'],
                ['pipe', 'w'],
            ],
            $pipes,
            $workingDirectory,
            $this->environmentWithOverrides($environment),
        );

        if (!is_resource($process)) {
            throw ProcessException::couldNotStart();
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return new ProcessResult(
            exitCode: proc_close($process),
            stdout: $stdout !== false ? $stdout : '',
            stderr: $stderr !== false ? $stderr : '',
        );
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>|null
     */
    private function environmentWithOverrides(array $overrides): ?array
    {
        if ($overrides === []) {
            return null;
        }

        // proc_open replaces inherited variables with overrides.
        // Keep them, then apply only the requested overrides.
        $inherited = getenv();

        return array_replace(is_array($inherited) ? $inherited : [], $overrides);
    }
}
