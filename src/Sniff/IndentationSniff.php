<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\IndentationFixer;
use DocbookCS\IndentationAnalyzer;
use DocbookCS\Source\File;

/**
 * @extends AbstractSniff<int>
 * @implements Fixable<int>
 */
final class IndentationSniff extends AbstractSniff implements Fixable
{
    private const string REPORTING_MESSAGE = 'Expected indentation of %d %s.';

    /** @var array<int, string> */
    private array $reportingMessages = [];

    public function __construct(
        private readonly IndentationAnalyzer $analyzer = new IndentationAnalyzer(),
    ) {}

    public static function getCode(): string
    {
        return 'DocbookCS.Indentation';
    }

    public static function getFixerClassName(): string
    {
        return IndentationFixer::class;
    }

    /**
     * @throws \InvalidArgumentException if a generated source range is inconsistent
     * @throws \OutOfBoundsException if a generated source range lies outside the source
     */
    public function process(\DOMDocument $document, File $file): array
    {
        $violations = [];

        foreach ($this->analyzer->analyze($file) as $mismatch) {

            $expectedDepth = $mismatch['expectedDepth'];

            $violations[] = $this->createViolation(
                $file->path,
                $this->reportingMessage($expectedDepth),
                [$mismatch['range']],
                $expectedDepth,
            );
        }

        return $violations;
    }

    private function reportingMessage(int $expectedDepth): string
    {
        return $this->reportingMessages[$expectedDepth] ??= sprintf(
            self::REPORTING_MESSAGE,
            $expectedDepth,
            $expectedDepth === 1 ? 'space' : 'spaces',
        );
    }
}
