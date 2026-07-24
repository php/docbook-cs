<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Config\SniffEntry;
use DocbookCS\Fix\FixerException;
use DocbookCS\Progress\NullProgress;
use DocbookCS\Progress\ProgressInterface;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Source\File;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\Violation;

final class RunCoordinator
{
    private ProgressInterface $progress;

    public function __construct(?ProgressInterface $progress = null)
    {
        $this->progress = $progress ?? new NullProgress();
    }

    /**
     * @throws \RuntimeException if a sniff class cannot be found or does not implement SniffInterface.
     * @throws FixerException
     */
    public function run(RunPlan $plan): Report
    {
        $startTime = microtime(true);

        $sniffs = $this->instantiateSniffs($plan->sniffs, $plan->mode);

        $report = new Report();
        $preprocessor = new EntityPreprocessor($plan->entities);
        $processor = new XmlFileProcessor($sniffs, $preprocessor, $report);

        $total = count($plan->targets);

        $this->progress->start($total);

        $index = 0;
        foreach ($plan->targets as $filePath => $fileChange) {
            $report->incrementFilesScanned();

            $content = @file_get_contents($filePath);

            if ($content === false) {
                $fileReport = new FileReport($filePath);
                $fileReport->addViolation(new Violation(
                    sniffCode: 'DocbookCS.Internal',
                    filePath: $filePath,
                    line: 0,
                    beginOffset: 0,
                    untilOffset: 0,
                    message: 'Could not read file.',
                    severity: Severity::ERROR,
                ));
            } else {
                $file = new File($filePath, $content);
                $result = $processor->process($file, $fileChange);
                $fileReport = $result->fileReport;

                if ($result->isModified() && @file_put_contents($filePath, $result->fixedContent()) === false) {
                    throw FixerException::cannotPersist($filePath);
                }
            }

            $violationCount = $fileReport->getViolationCount();

            if ($fileReport->hasViolations()) {
                $report->addFileReport($fileReport);
            }

            $this->progress->advance(++$index, $filePath, $violationCount);
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
    private function instantiateSniffs(array $entries, RunMode $mode): array
    {
        $sniffs = [];

        foreach ($entries as $entry) {
            $className = $entry->className;

            if (!class_exists($className)) {
                throw new \RuntimeException(
                    sprintf('Sniff class "%s" does not exist.', $className),
                );
            }

            $instance = new $className($mode);

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
