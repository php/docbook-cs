<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Report\FileReport;
use DocbookCS\Sniff\AttributeOrderSniff;
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Source\File;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class XmlFileProcessorPipelineTest extends TestCase
{
    #[Test]
    public function itKeepsTheActualSourcePathInViolations(): void
    {
        $workingDirectory = getcwd();
        self::assertIsString($workingDirectory);

        $filePath = tempnam($workingDirectory, 'docbook-cs-');
        self::assertIsString($filePath);

        try {
            file_put_contents($filePath, '<root xmlns="urn:test" xml:id="root"/>');

            $report = $this->process(
                $this->processor([new AttributeOrderSniff()]),
                '<root xmlns="urn:test" xml:id="root"/>',
                $filePath,
            );

            self::assertCount(1, $report->getViolations());
            self::assertSame($filePath, $report->getViolations()[0]->filePath);
            self::assertSame($filePath, $report->filePath);
        } finally {
            @unlink($filePath);
        }
    }

    #[Test]
    public function itAppliesFixesToTheOriginalSourceWhenEntitiesExpandBeforeTheViolation(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'docbook-cs-');
        self::assertIsString($filePath);
        $source = '<root>&prefix;<tag xmlns="urn:test" xml:id="id"/></root>';

        try {
            file_put_contents($filePath, $source);

            $processor = $this->processor(
                [new AttributeOrderSniff(RunMode::Fix)],
                new EntityPreprocessor([
                    'prefix' => 'expanded-content-before-tag',
                ]),
            );

            $this->processFile($processor, $filePath);

            self::assertSame(
                '<root>&prefix;<tag xml:id="id" xmlns="urn:test"/></root>',
                file_get_contents($filePath),
            );
        } finally {
            @unlink($filePath);
        }
    }

    private function process(XmlFileProcessor $processor, string $content, string $path = 'input.xml'): FileReport
    {
        return $processor->process(new File($path, $content))->fileReport;
    }

    private function processFile(XmlFileProcessor $processor, string $path): FileReport
    {
        $content = file_get_contents($path);
        self::assertIsString($content);

        $result = $processor->process(new File($path, $content));
        if ($result->isModified()) {
            file_put_contents($path, $result->fixedContent());
        }

        return $result->fileReport;
    }

    /** @param list<SniffInterface> $sniffs */
    private function processor(array $sniffs = [], ?EntityPreprocessor $pre = null): XmlFileProcessor
    {
        return new XmlFileProcessor(
            $sniffs,
            $pre ?? new EntityPreprocessor([]) // always pass array
        );
    }
}
