<?php

declare(strict_types=1);

namespace DocbookCS\Violation;

/** @template TFixerData = mixed */
final readonly class Violation
{
    /**
     * @param non-empty-list<SourceRange> $affectedRanges
     * @throws \InvalidArgumentException if the affected ranges are inconsistent
     */
    public function __construct(
        public string $sniffCode,
        public string $filePath,
        public string $message,
        public array $affectedRanges,
        public Severity $severity = Severity::WARNING,
        /**
         * Data passed from the sniff to its fixer. Fixers are responsible for validating its shape.
         *
         * @var TFixerData
         */
        public mixed $fixerData = null,
    ) {
        if ($affectedRanges === []) {
            throw new \InvalidArgumentException('A violation must affect at least one source range.');
        }

        $previousRange = null;
        foreach ($affectedRanges as $affectedRange) {
            if ($previousRange !== null && $affectedRange->beginOffset < $previousRange->untilOffset) {
                throw new \InvalidArgumentException('Violation source ranges must be ordered and cannot overlap.');
            }

            $previousRange = $affectedRange;
        }
    }

    public function rangeOne(): SourceRange
    {
        return $this->affectedRanges[0];
    }

    /** @throws \OutOfBoundsException if the violation does not have a second range */
    public function rangeTwo(): SourceRange
    {
        return $this->affectedRanges[1] ?? throw new \OutOfBoundsException('Violation does not have a second range.');
    }

    /** @throws \InvalidArgumentException if the internal source range is inconsistent */
    public static function fromFileReadFailure(string $filePath): self
    {
        return new self(
            sniffCode: 'DocbookCS.Internal',
            filePath: $filePath,
            message: 'Could not read file.',
            affectedRanges: [
                new SourceRange(0, 0, 0)
            ],
            severity: Severity::ERROR,
        );
    }

    /** @throws \InvalidArgumentException if the internal source range is inconsistent */
    public static function fromXmlParseError(string $filePath, ?\LibXMLError $error): self
    {
        return new self(
            sniffCode: 'DocbookCS.Internal',
            filePath: $filePath,
            message: 'XML parse error: ' . trim($error->message ?? 'unknown'),
            affectedRanges: [
                new SourceRange($error->line ?? 0, 0, 0),
            ],
            severity: Severity::ERROR,
        );
    }
}
