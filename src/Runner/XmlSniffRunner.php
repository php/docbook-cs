<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Fix\Fixer\Fixer;
use DocbookCS\Report\FileReport;
use DocbookCS\Sniff\Fixable;
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Source\File;
use DocbookCS\Violation\Violation;
use DocbookCS\Xml\XmlParser;

final readonly class XmlSniffRunner
{
    /** @param list<SniffInterface> $sniffs */
    public function __construct(
        private RunMode $mode,
        private array $sniffs,
        private EntityPreprocessor $preprocessor = new EntityPreprocessor(),
        private XmlParser $xmlParser = new XmlParser(),
        private ViolationScopeFilter $violationFilter = new ViolationScopeFilter(),
    ) {
    }

    /**
     * @return array{
     *     list<Violation>,
     *     list<array{
     *         sniffCode: string,
     *         fixerClass: class-string<Fixer>,
     *         violations: list<Violation>
     *     }>
     * }|\LibXMLError
     * @throws \InvalidArgumentException if an internal violation is inconsistent
     */
    public function runWithMetrics(File $file, FileReport $fileReport, RunScope $scope): array|\LibXMLError
    {
        return $fileReport->measureSniffing(
            fn(): array|\LibXMLError => $this->run($file, $fileReport, $scope),
        );
    }

    /**
     * @return array{
     *     list<Violation>,
     *     list<array{
     *         sniffCode: string,
     *         fixerClass: class-string<Fixer>,
     *         violations: list<Violation>
     *     }>
     * }|\LibXMLError
     * @throws \InvalidArgumentException if an internal violation is inconsistent
     */
    private function run(File $file, FileReport $fileReport, RunScope $scope): array|\LibXMLError
    {
        if (($document = $this->parseXml($file)) instanceof \LibXMLError) {
            return $document;
        }

        $violations = [];
        /** @var list<array{sniffCode: string, fixerClass: class-string<Fixer>, violations: list<Violation>}> $fixerBatches */
        $fixerBatches = [];

        foreach ($this->sniffs as $sniffer) {
            $sniffViolations = $fileReport->measureSniffer(
                $sniffer::getCode(),
                // run sniffers and filter violations
                fn() => $this->violationFilter->filter(
                    $sniffer->process($document, $file),
                    $document,
                    $file,
                    $scope,
                ),
            );

            array_push($violations, ...$sniffViolations);

            if ($sniffViolations === [] || !$this->mode->isFixMode() || !$sniffer instanceof Fixable) {
                continue;
            }

            $fixerBatches[] = [
                'sniffCode' => $sniffer::getCode(),
                'fixerClass' => $sniffer::getFixerClassName(),
                'violations' => $sniffViolations,
            ];
        }

        return [$violations, $fixerBatches];
    }

    /** @throws \InvalidArgumentException if the internal violation is inconsistent */
    private function parseXml(File $file): \DOMDocument|\LibXMLError
    {
        $content = $this->preprocessor->processForParsing($file->content);

        return $this->xmlParser->parseDocument($content);
    }
}
