<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Process;

use DocbookCS\Process\NativeProcessRunner;
use DocbookCS\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(NativeProcessRunner::class),
    //
    UsesClass(ProcessResult::class),
]
final class NativeProcessRunnerTest extends TestCase
{
    #[Test]
    public function itReturnsTheExitCodeAndOutputStreams(): void
    {
        $result = new NativeProcessRunner()->run(
            [PHP_BINARY, '-r', 'fwrite(STDOUT, "out"); fwrite(STDERR, "err"); exit(7);'],
            getcwd() ?: '.',
        );

        self::assertSame(7, $result->exitCode);
        self::assertSame('out', $result->stdout);
        self::assertSame('err', $result->stderr);
    }
}
