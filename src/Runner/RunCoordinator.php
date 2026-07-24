<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Config\SniffEntry;
use DocbookCS\Fix\FixerException;
use DocbookCS\Progress\NullProgress;
use DocbookCS\Progress\ProgressInterface;
use DocbookCS\Report\Report;
use DocbookCS\Report\ReportException;
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Source\File;
use DocbookCS\Violation\Violation;

final class RunCoordinator
{
    private ProgressInterface $progress;

    public function __construct(
        ?ProgressInterface $progress = null,
        private readonly bool $collectPerformance = false,
    ) {
        $this->progress = $progress ?? new NullProgress();
    }

    /**
     * @throws \InvalidArgumentException if an internal violation is inconsistent
     * @throws ReportException if violations are added in an invalid order
     * @throws \RuntimeException if a sniff class cannot be found or does not implement SniffInterface.
     * @throws FixerException
     */
    public function runWithMetrics(RunPlan $plan): Report
    {
        $report = new Report($this->collectPerformance);

        return $report->measureWallTime(
            fn(): Report => $this->run($plan, $report),
        );
    }

    /**
     * @throws \InvalidArgumentException if an internal violation is inconsistent
     * @throws ReportException if violations are added in an invalid order
     * @throws \RuntimeException if a sniff class cannot be found or does not implement SniffInterface.
     * @throws FixerException
     */
    private function run(RunPlan $plan, Report $report): Report
    {
        $processor = new XmlFileProcessor(new XmlSniffRunner(
            $plan->mode,
            $this->instantiateSniffs($plan->sniffs),
            new EntityPreprocessor($plan->entities),
        ));

        $this->progress->start(count($plan->targets));

        foreach ($plan->targets as $filePath => $fileChange) {
            $fileReport = $report->newFileReport($filePath);

            $content = @file_get_contents($filePath);

            if ($content === false) {
                $fileReport->addFailedViolation(Violation::fromFileReadFailure($filePath));

                $this->progress->advance($filePath, $fileReport->getFinalViolationCount());
                continue;
            }

            $file = new File($filePath, $content);
            $scope = RunScope::fromFileAndFileChange($file, $fileChange);

            $fixedFile = $processor->process($file, $fileReport, $scope);

            if ($fixedFile !== null && @file_put_contents($filePath, $fixedFile->content) === false) {
                throw FixerException::cannotPersist($filePath);
            }

            $this->progress->advance($filePath, $fileReport->getFinalViolationCount());
        }

        $this->progress->finish();

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
            $className = $entry->className;

            if (!class_exists($className)) {
                throw new \RuntimeException(
                    sprintf('Sniff class "%s" does not exist.', $className),
                );
            }

            $instance = new $className();

            if (!$instance instanceof SniffInterface) {
                throw new \RuntimeException(
                    sprintf('Class "%s" does not implement %s.', $className, SniffInterface::class),
                );
            }

            foreach ($entry->properties as $name => $value) {
                $instance->setProperty($name, $value);
            }

            $sniffs[] = $instance;
        }

        return $sniffs;
    }
}
