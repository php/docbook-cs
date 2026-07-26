<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Report\FileReport;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunScope;
use DocbookCS\Runner\XmlSniffRunner;
use DocbookCS\Sniff\AttributeOrderSniff;
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
        $file = new File(
            '/project/reference/file.xml',
            '<root xmlns="urn:test" xml:id="root"/>',
        );
        $fileReport = new FileReport($file->path);

        new XmlFileProcessor(
            new XmlSniffRunner(RunMode::Sniff, [new AttributeOrderSniff()]),
        )->process($file, $fileReport, RunScope::fromFileAndFileChange($file, null));

        self::assertSame(1, $fileReport->getFinalViolationCount());
        self::assertSame($file->path, $fileReport->finalViolations[0]->filePath);
        self::assertSame($file->path, $fileReport->filePath);
    }

    #[Test]
    public function itAppliesFixesToTheOriginalSourceWhenEntitiesExpandBeforeTheViolation(): void
    {
        $file = new File(
            'input.xml',
            '<root>&prefix;<tag xmlns="urn:test" xml:id="id"/></root>',
        );
        $fileReport = new FileReport($file->path);
        $fixedFile = new XmlFileProcessor(
            new XmlSniffRunner(
                RunMode::Fix,
                [new AttributeOrderSniff()],
                new EntityPreprocessor([
                    'prefix' => 'expanded-content-before-tag',
                ]),
            ),
        )->process($file, $fileReport, RunScope::fromFileAndFileChange($file, null));

        self::assertNotNull($fixedFile);
        self::assertSame(
            '<root>&prefix;<tag xml:id="id" xmlns="urn:test"/></root>',
            $fixedFile->content,
        );
    }

    #[Test] // TODO: should be integration
    public function itHandlesEntitiesWithoutParseErrors(): void
    {
        $xml = $this->xml(
            '<!DOCTYPE chapter SYSTEM "docbook.dtd">
        <chapter>
          <simpara>&link.superglobals; &php.ini; &amp;</simpara>
        </chapter>'
        );

        $file = new File('input.xml', $xml);
        $fileReport = new FileReport($file->path);
        new XmlFileProcessor(
            new XmlSniffRunner(RunMode::Sniff, [], new EntityPreprocessor([
                'link.superglobals' => '',
                'php.ini' => '',
            ])),
        )->process(
            $file,
            $fileReport,
            RunScope::fromFileAndFileChange($file, null),
        );

        self::assertFalse($fileReport->hasFinalViolations());
    }

    #[Test] // TODO: should be integration
    public function itUsesCustomPreprocessor(): void
    {
        $xml = $this->xml('<chapter><simpara>&custom.entity;</simpara></chapter>');
        $file = new File('input.xml', $xml);
        $fileReport = new FileReport($file->path);
        new XmlFileProcessor(
            new XmlSniffRunner(
                RunMode::Sniff,
                [],
                new EntityPreprocessor(['custom.entity' => '[X]']),
            ),
        )->process(
            $file,
            $fileReport,
            RunScope::fromFileAndFileChange($file, null),
        );

        self::assertFalse($fileReport->hasFinalViolations());
    }

    private function xml(string $body): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
$body
XML;
    }
}
