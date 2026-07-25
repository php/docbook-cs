<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Git;

use DocbookCS\Git\GitClient;
use DocbookCS\Git\GitException;
use DocbookCS\Process\ProcessException;
use DocbookCS\Process\ProcessResult;
use DocbookCS\Process\ProcessRunnerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(GitClient::class),
    CoversClass(GitException::class),
    CoversClass(ProcessException::class),
]
final class GitClientTest extends TestCase
{
    #[Test]
    public function itTranslatesProcessFailures(): void
    {
        $processRunner = new class implements ProcessRunnerInterface {
            public function run(array $command, string $workingDirectory, array $environment = []): ProcessResult
            {
                throw ProcessException::couldNotStart();
            }
        };

        try {
            new GitClient($processRunner)->repoRoot('.');
            self::fail('Expected GitException was not thrown.');
        } catch (GitException $exception) {
            self::assertSame('Could not start process.', $exception->getMessage());
            self::assertInstanceOf(
                ProcessException::class,
                $exception->getPrevious(),
            );
        }
    }
}
