<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Config\ConfigData;
use DocbookCS\Config\SniffEntry;
use DocbookCS\Diff\Diff;
use DocbookCS\Path\EntityResolver;
use DocbookCS\Path\PathLoader;
use DocbookCS\Path\PathMatcher;
use DocbookCS\Progress\NullProgress;
use DocbookCS\Progress\ProgressInterface;
use DocbookCS\Report\Report;
use DocbookCS\Sniff\SniffInterface;

final class SniffRunner
{
    private ProgressInterface $progress;

    public function __construct(?ProgressInterface $progress = null)
    {
        $this->progress = $progress ?? new NullProgress();
    }

    /**
     * @param list<string>|null $overridePaths
     * @throws \RuntimeException if a sniff class cannot be found or does not implement SniffInterface.
     * @throws \UnexpectedValueException if no files are found to scan.
     */
    public function run(ConfigData $config, ?array $overridePaths = null, ?Diff $diff = null): Report
    {
        $startTime = microtime(true);

        $sniffs = $this->instantiateSniffs($config->getSniffs());

        $matcher = new PathMatcher($config->getBasePath(), $config->getExcludePatterns());

        $includePaths = $overridePaths ?? $config->getIncludePaths();

        $entityResolver = new EntityResolver($config->getProjectRoots(), $config->getEntityPaths());
        $entities = $entityResolver->resolve();

        $pathLoader = new PathLoader($includePaths, $matcher);
        $files = $pathLoader->loadPaths();

        if ($diff !== null) {
            $files = array_values(array_filter(
                $files,
                static fn(string $file): bool => $diff->changeFor($file) !== null,
            ));
        }

        $report = new Report();
        $preprocessor = new EntityPreprocessor($entities);
        $processor = new XmlFileProcessor($sniffs, $preprocessor, $report);

        $total = count($files);

        $this->progress->start($total);

        foreach ($files as $index => $file) {
            $report->incrementFilesScanned();

            $changedLines = $diff?->changeFor($file)?->addedLineNumbers;

            $fileReport = $processor->processFile(
                $file,
                $changedLines,
                $this->makeRelative($file),
            );

            $violationCount = $fileReport->getViolationCount();

            if ($fileReport->hasViolations()) {
                $report->addFileReport($fileReport);
            }

            $this->progress->advance($index + 1, $file, $violationCount);
        }

        $this->progress->finish();

        $report->setTotalTime(microtime(true) - $startTime);

        return $report;
    }

    /**
     * @param list<SniffEntry> $entries
     * @return list<SniffInterface>
     * @throws \RuntimeException if a sniff class cannot be found or does not implement SniffInterface.
     */
    private function instantiateSniffs(array $entries): array
    {
        $sniffs = [];

        foreach ($entries as $entry) {
            $className = $entry->getClassName();

            if (!class_exists($className)) {
                throw new \RuntimeException(sprintf(
                    'Sniff class "%s" does not exist.',
                    $className,
                ));
            }

            $instance = new $className();

            if (!$instance instanceof SniffInterface) {
                throw new \RuntimeException(sprintf(
                    'Class "%s" does not implement %s.',
                    $className,
                    SniffInterface::class,
                ));
            }

            foreach ($entry->getProperties() as $name => $value) {
                $instance->setProperty($name, $value);
            }

            $sniffs[] = $instance;
        }

        return $sniffs;
    }

    private function makeRelative(string $absolutePath): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return $absolutePath; // @codeCoverageIgnore
        }

        $prefix = rtrim(str_replace('\\', '/', $cwd), '/') . '/';
        $normalized = str_replace('\\', '/', $absolutePath);

        if (str_starts_with($normalized, $prefix)) {
            return substr($normalized, strlen($prefix));
        }

        return $absolutePath; // @codeCoverageIgnore
    }
}
