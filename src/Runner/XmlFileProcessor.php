<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Fix\FixerException;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\ReportException;
use DocbookCS\Source\File;
use DocbookCS\Violation\Violation;

final readonly class XmlFileProcessor
{
    private const int MAX_FIX_PASSES = 20;

    public function __construct(
        private XmlSniffRunner $xmlSniffRunner,
        private XmlFixRunner $xmlFixRunner = new XmlFixRunner(),
    ) {
    }

    /**
     * @throws FixerException
     * @throws \InvalidArgumentException if an internal violation is inconsistent
     * @throws ReportException if violations are added in an invalid order
     */
    public function process(File $file, FileReport $fileReport, RunScope $scope): ?File
    {
        $seenContentHashes = [hash('sha256', $file->content) => true];
        $initialViolations = null;
        $changed = false;

        while (true) {
            $sniffingResult = $this->xmlSniffRunner->runWithMetrics($file, $fileReport, $scope);

            if ($sniffingResult instanceof \LibXMLError) {
                if ($changed) {
                    throw FixerException::invalidFixedXml($file->path);
                }

                $fileReport->addFailedViolation(Violation::fromXmlParseError($file->path, $sniffingResult));
                return null;
            }

            [$passViolations, $fixerBatches] = $sniffingResult;

            $initialViolations ??= $passViolations;

            if ($fixerBatches === []) {
                break;
            }

            $fixResult = $this->xmlFixRunner->runWithMetrics($file, $fileReport, $fixerBatches);

            if ($fixResult->applied === 0) {
                break;
            }

            $fixedContentHash = hash('sha256', $fixResult->file->content);

            if ($fileReport->fixingPasses > self::MAX_FIX_PASSES || isset($seenContentHashes[$fixedContentHash])) {
                throw FixerException::didNotConverge($file->path);
            }

            $seenContentHashes[$fixedContentHash] = true;
            $scope = $scope->after($fixResult->appliedFixes);
            $file = $fixResult->file;
            $changed = true;
        }

        $fileReport->addFoundViolations($initialViolations);

        if (!$changed) {
            return null;
        }

        $fileReport->addFinalViolations($passViolations);
        $fileReport->markChanged();

        return $file;
    }
}
