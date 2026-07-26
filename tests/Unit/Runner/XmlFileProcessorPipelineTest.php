<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixResult;
use DocbookCS\Fix\Fixer\AttributeOrderFixer;
use DocbookCS\Fix\Fixer\SimparaFixer;
use DocbookCS\Report\FileReport;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunScope;
use DocbookCS\Runner\ViolationScopeFilter;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Runner\XmlFixRunner;
use DocbookCS\Runner\XmlSniffRunner;
use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Sniff\AttributeOrderSniff;
use DocbookCS\Sniff\Fixable;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use DocbookCS\Xml\XmlParser;
use DocbookCS\Tests\Support\XmlHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(XmlFileProcessor::class),
    //
    UsesClass(AbstractSniff::class),
    UsesClass(AttributeOrderFixer::class),
    UsesClass(AttributeOrderSniff::class),
    UsesClass(EntityPreprocessor::class),
    UsesClass(File::class),
    UsesClass(FileReport::class),
    UsesClass(Fix::class),
    UsesClass(FixApplier::class),
    UsesClass(FixPlan::class),
    UsesClass(FixResult::class),
    UsesClass(RunMode::class),
    UsesClass(RunScope::class),
    UsesClass(SimparaFixer::class),
    UsesClass(SourceRange::class),
    UsesClass(Violation::class),
    UsesClass(ViolationScopeFilter::class),
    UsesClass(XmlFixRunner::class),
    UsesClass(XmlParser::class),
    UsesClass(XmlSniffRunner::class),
]
final class XmlFileProcessorPipelineTest extends TestCase
{
    use XmlHelper;

    #[Test]
    public function itReportsXmlParseErrors(): void
    {
        $file = new File('input.xml', '<root>');
        $fileReport = new FileReport($file->path);

        $fixedFile = new XmlFileProcessor(
            new XmlSniffRunner(RunMode::Sniff, []),
        )->process($file, $fileReport, RunScope::fromFileAndFileChange($file, null));

        self::assertNull($fixedFile);
        self::assertSame(1, $fileReport->getFinalViolationCount());
        self::assertSame('DocbookCS.Internal', $fileReport->finalViolations[0]->sniffCode);
    }

    #[Test]
    public function itStopsWhenAStaleFixCannotBeApplied(): void
    {
        $file = new File('input.xml', '<root/>');
        $fileReport = new FileReport($file->path);
        $sniff = new class extends AbstractSniff implements Fixable {
            public static function getCode(): string
            {
                return 'Test.StaleFix';
            }

            public static function getFixerClassName(): string
            {
                return SimparaFixer::class;
            }

            public function process(\DOMDocument $document, File $file): array
            {
                return [$this->createViolation(
                    $file->path,
                    'Stale fix.',
                    [
                        new SourceRange(1, 0, 4, 'para'),
                        new SourceRange(1, 4, 8, 'para'),
                    ],
                )];
            }
        };

        $fixedFile = new XmlFileProcessor(
            new XmlSniffRunner(RunMode::Fix, [$sniff]),
        )->process($file, $fileReport, RunScope::fromFileAndFileChange($file, null));

        self::assertNull($fixedFile);
        self::assertSame(1, $fileReport->getFoundViolationCount());
        self::assertSame(1, $fileReport->getFinalViolationCount());
        self::assertSame(1, $fileReport->fixingPasses);
        self::assertFalse($fileReport->changed);
    }

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

    #[Test]
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

    #[Test]
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
}
