<?php

declare(strict_types=1);

namespace DocbookCS\Report\Reporter;

use DocbookCS\RelativePath;
use DocbookCS\Report\Report;

final class CheckstyleReporter implements ReporterInterface
{
    /** @throws \LogicException if the DOM rejects a node, which fixed element and attribute names rule out */
    public function generate(Report $report): string
    {
        try {
            return $this->buildXml($report);
        } catch (\DOMException $e) {
            throw new \LogicException('Could not build the checkstyle report.', 0, $e);
        }
    }

    /** @throws \DOMException */
    private function buildXml(Report $report): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('checkstyle');
        $root->setAttribute('version', '3.0');
        $dom->appendChild($root);

        $comment = $dom->createComment(
            sprintf(' total runtime: %.3fs ', $report->totalTime)
        );
        $root->appendChild($comment);

        foreach ($report->fileReports as $fileReport) {
            if (!$fileReport->hasFinalViolations()) {
                continue;
            }

            $fileNode = $dom->createElement('file');
            $fileNode->setAttribute('name', RelativePath::fromWorkingDirectory($fileReport->filePath));

            foreach ($fileReport->finalViolations as $violation) {
                $errorNode = $dom->createElement('error');
                $errorNode->setAttribute('line', (string) $violation->rangeOne()->line);
                $errorNode->setAttribute('severity', $violation->severity->value);
                $errorNode->setAttribute('message', $violation->message);
                $errorNode->setAttribute('source', $violation->sniffCode);
                $fileNode->appendChild($errorNode);
            }

            $root->appendChild($fileNode);
        }

        return $dom->saveXML() ?: '';
    }
}
