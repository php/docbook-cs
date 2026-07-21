<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Diff\FileChange;
use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixerException;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Sniff\Fixable;
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Source\File;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\Violation;

final readonly class XmlFileProcessor
{
    private const int MAX_FIX_PASSES = 20;

    /** @var list<SniffInterface> */
    private array $sniffs;

    private EntityPreprocessor $preprocessor;

    private Report $report;

    private ViolationScopeFilter $violationScopeFilter;

    /** @param list<SniffInterface> $sniffs */
    public function __construct(
        array $sniffs,
        ?EntityPreprocessor $preprocessor = null,
        ?Report $report = null,
    ) {
        $this->sniffs = $sniffs;
        $this->preprocessor = $preprocessor ?? new EntityPreprocessor([]);
        $this->report = $report ?? new Report();
        $this->violationScopeFilter = new ViolationScopeFilter();
    }

    /** @throws FixerException */
    public function process(File $initialFile, ?FileChange $fileChange = null): XmlProcessingResult
    {
        $fileReport = new FileReport($initialFile->path);
        $currentFile = $initialFile;
        $scope = $fileChange === null
            ? SourceScope::wholeFile()
            : SourceScope::fromFileChange($initialFile, $fileChange);
        $seenContentHashes = [hash('sha256', $currentFile->content) => true];
        $fixPasses = 0;

        while (true) {
            $passReport = new FileReport($currentFile->path);

            $document = $this->parseXml($currentFile, $passReport);
            if ($document === null) {
                if ($currentFile->content !== $initialFile->content) {
                    throw FixerException::invalidFixedXml($currentFile->path);
                }

                break;
            }

            $fixes = $this->runSniffs($document, $currentFile, $passReport, $scope);

            if ($fixes === []) {
                break;
            }

            $fixResult = $this->report->measureFixing(
                static fn() => new FixApplier()->apply($currentFile, $fixes),
            );
            $this->report->recordFixPass($fixResult->applied, $fixResult->skipped);

            if ($fixResult->applied === 0) {
                break;
            }

            $fixPasses++;
            $fixedContentHash = hash('sha256', $fixResult->file->content);

            if (
                $fixPasses > self::MAX_FIX_PASSES
                || $fixResult->file->content === $currentFile->content
                || isset($seenContentHashes[$fixedContentHash])
            ) {
                throw FixerException::didNotConverge($currentFile->path);
            }

            $seenContentHashes[$fixedContentHash] = true;
            $scope = $scope->after($fixResult->appliedFixes);
            $currentFile = $fixResult->file;
        }

        $fileReport->addViolations($passReport->getViolations());

        return new XmlProcessingResult(
            fileReport: $fileReport,
            initialFile: $initialFile,
            currentFile: $currentFile,
        );
    }

    /**
     * @return list<Fix|FixPlan>
     * @throws FixerException
     */
    private function runSniffs(\DOMDocument $document, File $file, FileReport $fileReport, SourceScope $scope): array
    {
        $fixes = [];

        foreach ($this->sniffs as $sniff) {
            $sniffViolations = $this->report->measureSniff(
                $sniff::getCode(),
                static fn() => $sniff->process($document, $file),
            );

            $relevantViolations = $this->violationScopeFilter->filter($sniffViolations, $document, $file, $scope);

            $fileReport->addViolations($relevantViolations);

            if (!$sniff->mode->isFixMode() || !$sniff instanceof Fixable) {
                continue;
            }

            $sniffFixes = $this->report->measureFixing(
                fn() => $this->createFixes($sniff, $relevantViolations)
            );

            $fixes = array_merge($fixes, $sniffFixes);
        }

        return $fixes;
    }

    private function parseXml(File $file, FileReport $fileReport): ?\DOMDocument
    {
        $content = $this->preprocessor->processForParsing($file->content);

        $previousUseErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->preserveWhiteSpace = true;

        // LIBXML_NONET prevents network access.
        // No LIBXML_DTDLOAD needed since we stripped the DOCTYPE.
        $loaded = $document->loadXML($content, LIBXML_NONET);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        if (!$loaded) {
            $message = $errors !== []
                ? trim($errors[0]->message)
                : 'Unknown XML parse error'; // @codeCoverageIgnore

            $fileReport->addViolation(new Violation(
                sniffCode: 'DocbookCS.Internal',
                filePath: $file->path,
                line: $errors !== [] ? $errors[0]->line : 0,
                beginOffset: 0,
                untilOffset: 0,
                message: 'XML parse error: ' . $message,
                severity: Severity::ERROR,
            ));
            return null;
        }

        return $document;
    }

    /**
     * @param list<Violation> $violations
     * @return list<Fix|FixPlan>
     * @throws FixerException
     */
    private function createFixes(Fixable $sniff, array $violations): array
    {
        $fixes = [];
        $fixer = new ($sniff::fixerClassName());

        foreach ($violations as $violation) {
            $fixes[] = $fixer->process($violation);
        }

        return $fixes;
    }
}
