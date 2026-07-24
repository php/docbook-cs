<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Process;

use DocbookCS\Process\NativeProcessRunner;
use DocbookCS\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(NativeProcessRunner::class),
    CoversClass(ProcessResult::class),
]
final class NativeProcessRunnerTest extends TestCase
{
    #[Test]
    public function itCapturesTheProcessResult(): void
    {
        $processRunner = new NativeProcessRunner();
        $result = $processRunner->run(
            [
                PHP_BINARY,
                '-r',
                'fwrite(STDOUT, "output"); fwrite(STDERR, "error"); exit(7);',
            ],
            getcwd() ?: '.',
        );

        self::assertSame(7, $result->exitCode);
        self::assertSame('output', $result->stdout);
        self::assertSame('error', $result->stderr);
    }

    #[Test]
    public function itPassesEnvironmentVariablesToTheProcess(): void
    {
        $result = new NativeProcessRunner()->run(
            [PHP_BINARY, '-r', 'fwrite(STDOUT, getenv("DOCBOOK_CS_TEST") ?: "");'],
            getcwd() ?: '.',
            ['DOCBOOK_CS_TEST' => 'value'],
        );

        self::assertSame(0, $result->exitCode);
        self::assertSame('value', $result->stdout);
    }
}
