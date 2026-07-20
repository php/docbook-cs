<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Config\SniffEntry;
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
     * @throws \RuntimeException if a sniff class cannot be found or does not implement SniffInterface.
     */
    public function run(RunPlan $plan): Report
    {
        $startTime = microtime(true);

        $sniffs = $this->instantiateSniffs($plan->sniffs);

        $report = new Report();
        $preprocessor = new EntityPreprocessor($plan->entities);
        $processor = new XmlFileProcessor($sniffs, $preprocessor, $report);

        $total = count($plan->targets);

        $this->progress->start($total);

        $index = 0;
        foreach ($plan->targets as $file => $fileChange) {
            $report->incrementFilesScanned();

            $changedLines = $fileChange !== null
                ? array_values(array_unique([...$fileChange->addedLineNumbers, ...$fileChange->deletionAnchors]))
                : null;

            $fileReport = $processor->processFile(
                $file,
                $changedLines,
                $file,
            );

            $violationCount = $fileReport->getViolationCount();

            if ($fileReport->hasViolations()) {
                $report->addFileReport($fileReport);
            }

            $this->progress->advance(++$index, $file, $violationCount);
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
}
