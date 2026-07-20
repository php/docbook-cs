<?php

declare(strict_types=1);

namespace DocbookCS\Report\Reporter;

use DocbookCS\RelativePath;
use DocbookCS\Report\Report;

final class JsonReporter implements ReporterInterface
{
    public function generate(Report $report): string
    {
        $data = [
            'totals' => [
                'files_scanned' => $report->getFilesScanned(),
                'violations' => $report->getTotalViolations(),
                'errors' => $report->getTotalErrors(),
                'warnings' => $report->getTotalWarnings(),
            ],
            'files' => [],
            'performance' => [
                'total_runtime_seconds' => $report->getTotalTime(),
            ],
        ];

        foreach ($report->getFileReports() as $fileReport) {
            if (!$fileReport->hasViolations()) {
                continue;
            }

            $violations = [];
            foreach ($fileReport->getViolations() as $violation) {
                $violations[] = [
                    'line' => $violation->line,
                    'severity' => $violation->severity,
                    'message' => $violation->message,
                    'source' => $violation->sniffCode,
                ];
            }

            $data['files'][RelativePath::fromWorkingDirectory($fileReport->filePath)] = [
                'violations' => count($violations),
                'messages' => $violations,
            ];
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}
