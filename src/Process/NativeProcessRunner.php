<?php

declare(strict_types=1);

namespace DocbookCS\Process;

final class NativeProcessRunner implements ProcessRunnerInterface
{
    public function run(array $command, string $workingDirectory): ProcessResult
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
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start process.');
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
}
