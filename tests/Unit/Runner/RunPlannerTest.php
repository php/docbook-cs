<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Config\ConfigData;
use DocbookCS\Diff\Diff;
use DocbookCS\Diff\DiffParser;
use DocbookCS\Diff\DiffProviderInterface;
use DocbookCS\Diff\FileChange;
use DocbookCS\Path\DiffPathLoader;
use DocbookCS\Path\EntityResolver;
use DocbookCS\Path\PathMatcher;
use DocbookCS\Runner\RunPlan;
use DocbookCS\Runner\RunPlanner;
use DocbookCS\Runner\RunScopeResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(RunPlan::class),
    CoversClass(RunPlanner::class),
    //
    UsesClass(ConfigData::class),
    UsesClass(Diff::class),
    UsesClass(DiffParser::class),
    UsesClass(DiffPathLoader::class),
    UsesClass(EntityResolver::class),
    UsesClass(FileChange::class),
    UsesClass(PathMatcher::class),
    UsesClass(RunScopeResolver::class),
]
final class RunPlannerTest extends TestCase
{
    #[Test]
    public function itUsesTheContributionDiffWhenNoInputIsProvided(): void
    {
        $config = new ConfigData(
            projectRoots: [],
            sniffs: [],
            includePaths: [],
            excludePatterns: [],
            entityPaths: [],
            basePath: getcwd() ?: '.',
        );

        $diffProvider = $this->createMock(DiffProviderInterface::class);
        $diffProvider
            ->expects(self::once())
            ->method('for')
            ->willReturn(<<<'DIFF'
diff --git a/nonexistent.xml b/nonexistent.xml
--- a/nonexistent.xml
+++ b/nonexistent.xml
@@ -1 +1 @@
-old
+new
DIFF);

        $planner = new RunPlanner($config, diffProvider: $diffProvider);

        self::assertSame([], $planner->plan([], null)->targets);
    }
}
