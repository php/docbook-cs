<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\Fixer\Fixer;
use DocbookCS\Fix\FixResult;
use DocbookCS\Report\FileReport;
use DocbookCS\Source\File;
use DocbookCS\Violation\Violation;

final class XmlFixRunner
{
    /**
     * @param list<array{
     *     sniffCode: string,
     *     fixerClass: class-string<Fixer>,
     *     violations: list<Violation>
     * }> $fixerBatches
     */
    public function runWithMetrics(File $file, FileReport $fileReport, array $fixerBatches): FixResult
    {
        return $fileReport->measureFixing(
            fn(): FixResult => $this->run($file, $fileReport, $fixerBatches),
        );
    }

    /**
     * @param list<array{
     *     sniffCode: string,
     *     fixerClass: class-string<Fixer>,
     *     violations: list<Violation>
     * }> $fixerBatches
     */
    private function run(File $file, FileReport $fileReport, array $fixerBatches): FixResult
    {
        $fixes = [];
        $fileReport->recordFixingPass();

        foreach ($fixerBatches as $batch) {
            $batchFixes = $fileReport->measureFixer(
                $batch['sniffCode'],
                static function () use ($batch): array {
                    $fixes = [];
                    $fixerClass = $batch['fixerClass'];
                    $fixer = new $fixerClass();

                    foreach ($batch['violations'] as $violation) {
                        $fixes[] = $fixer->process($violation);
                    }

                    return $fixes;
                },
            );

            array_push($fixes, ...$batchFixes);
        }

        return new FixApplier()->apply($file, $fixes);
    }
}
