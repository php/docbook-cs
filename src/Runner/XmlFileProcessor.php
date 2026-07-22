<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Report\Severity;
use DocbookCS\Report\Violation;
use DocbookCS\Sniff\SniffInterface;

final class XmlFileProcessor
{
    /** @var list<SniffInterface> */
    private array $sniffs;

    private EntityPreprocessor $preprocessor;

    private Report $report;

    /** @param list<SniffInterface> $sniffs */
    public function __construct(
        array $sniffs,
        ?EntityPreprocessor $preprocessor = null,
        ?Report $report = null,
    ) {
        $this->sniffs = $sniffs;
        $this->preprocessor = $preprocessor ?? new EntityPreprocessor([]);
        $this->report = $report ?? new Report();
    }

    /** @param list<int>|null $changedLines */
    public function processFile(string $filePath, ?array $changedLines = null, string $reportPath = ''): FileReport
    {
        $effectivePath = $reportPath !== '' ? $reportPath : $filePath;
        $fileReport = new FileReport($effectivePath);

        $content = @file_get_contents($filePath);
        if ($content === false) {
            $fileReport->addViolation(new Violation(
                sniffCode: 'DocbookCS.Internal',
                filePath: $effectivePath,
                line: 0,
                message: 'Could not read file.',
                severity: Severity::ERROR,
            ));
            return $fileReport;
        }

        return $this->processContent($content, $effectivePath, $fileReport, $changedLines);
    }

    /** @param list<int>|null $changedLines */
    public function processString(
        string $xmlContent,
        string $pseudoPath = 'input.xml',
        ?array $changedLines = null,
    ): FileReport {
        $fileReport = new FileReport($pseudoPath);

        return $this->processContent($xmlContent, $pseudoPath, $fileReport, $changedLines);
    }

    /** @param list<int>|null $changedLines */
    private function processContent(
        string $content,
        string $filePath,
        FileReport $fileReport,
        ?array $changedLines = null,
    ): FileReport {
        $content = $this->preprocessor->processForParsing($content);

        $document = $this->parseXml($content, $filePath, $fileReport);
        if ($document === null) {
            return $fileReport;
        }

        $violations = [];
        foreach ($this->sniffs as $sniff) {
            $start = microtime(true);

            foreach ($sniff->process($document, $content, $filePath) as $violation) {
                $violations[] = $violation;
            }

            $this->report->addSniffTime($sniff->getCode(), microtime(true) - $start);
        }

        if ($changedLines !== null) {
            $violations = $this->filterRelevantViolations($violations, $document, $changedLines);
        }

        foreach ($violations as $violation) {
            $fileReport->addViolation($violation);
        }

        return $fileReport;
    }

    private function parseXml(string $content, string $filePath, FileReport $fileReport): ?\DOMDocument
    {
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
                filePath: $filePath,
                line: $errors !== [] ? $errors[0]->line : 0,
                message: 'XML parse error: ' . $message,
                severity: Severity::ERROR,
            ));
            return null;
        }

        return $document;
    }

    /**
     * @param list<Violation> $violations
     * @param list<int> $changedLines
     * @return list<Violation>
     */
    private function filterRelevantViolations(array $violations, \DOMDocument $document, array $changedLines): array
    {
        /** @var array<int, int> $changedSet */
        $changedSet = array_flip($changedLines);

        return array_values(array_filter(
            $violations,
            fn(Violation $v) => $this->isViolationRelevant($v, $document, $changedLines, $changedSet),
        ));
    }

    /**
     * @param list<int> $changedLines
     * @param array<int, int> $changedSet
     */
    private function isViolationRelevant(
        Violation $violation,
        \DOMDocument $document,
        array $changedLines,
        array $changedSet,
    ): bool {
        if (isset($changedSet[$violation->line])) {
            return true;
        }

        $violationElement = $this->firstElementOnLine($document, $violation->line);
        if ($violationElement === null) {
            return false;
        }

        $endLine = $this->computeElementEndLine($violationElement);

        foreach ($changedLines as $changed) {
            $owner = $this->innermostContaining($violationElement, $changed, $endLine);
            if ($owner === $violationElement) {
                return true;
            }

            if ($owner !== null && $owner->parentNode === $violationElement) {
                return true;
            }
        }

        return false;
    }

    private function firstElementOnLine(\DOMDocument $document, int $line): ?\DOMElement
    {
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element->getLineNo() === $line) {
                return $element;
            }
        }

        return null;
    }

    private function innermostContaining(\DOMElement $element, int $line, int $endLine): ?\DOMElement
    {
        if ($line > $endLine || $line < $element->getLineNo()) {
            return null;
        }

        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        $count = count($children);
        foreach ($children as $i => $iValue) {
            $child = $iValue;

            $childEnd = $endLine;
            for ($j = $i + 1; $j < $count; $j++) {
                $nextLine = $children[$j]->getLineNo();
                if ($nextLine > $child->getLineNo()) {
                    $childEnd = $nextLine - 1;
                    break;
                }
            }
            $childEnd = min($childEnd, $this->computeElementEndLine($child));

            $deeper = $this->innermostContaining($child, $line, $childEnd);
            if ($deeper !== null) {
                return $deeper;
            }
        }

        return $element;
    }

    private function computeElementEndLine(\DOMElement $element): int
    {
        $max = $element->getLineNo();

        foreach ($element->childNodes as $child) {
            $line = $child->getLineNo();
            if ($line > $max) {
                $max = $line;
            }

            if ($child instanceof \DOMElement) {
                $childEnd = $this->computeElementEndLine($child);
                if ($childEnd > $max) {
                    $max = $childEnd;
                }
            }
        }

        return $max;
    }
}
