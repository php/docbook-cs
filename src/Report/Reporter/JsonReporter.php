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
                'files_scanned' => $report->getScannedFilesCount(),
                'violations' => $report->getTotalFinalViolationCount(),
                'errors' => $report->getTotalErrorLevelViolationCount(),
                'warnings' => $report->getTotalWarningLevelViolationCount(),
            ],
            'files' => [],
            'fixing' => [
                'files_changed' => $report->getChangedFilesCount(),
                'fixes_applied' => $report->getAppliedFixesCount(),
                'fixes_skipped' => $report->getSkippedFixesCount(),
                'fixing_passes' => $report->getFixingPassesCount(),
            ],
            'performance' => [
                'total_runtime_seconds' => $report->totalTime,
            ],
        ];

        foreach ($report->fileReports as $fileReport) {
            if (!$fileReport->hasFinalViolations()) {
                continue;
            }

            $violations = [];
            foreach ($fileReport->finalViolations as $violation) {
                $violations[] = [
                    'line' => $violation->rangeOne()->line,
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
