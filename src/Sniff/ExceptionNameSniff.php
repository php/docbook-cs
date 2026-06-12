<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

/**
 * Detects exception/error class names wrapped in <classname> that
 * should use <exceptionname> instead.
 *
 * DocBook provides <exceptionname> specifically for "the name of an
 * exception." When a <classname> element's text content matches a
 * known exception/error pattern, this sniff flags it.
 */
final class ExceptionNameSniff extends AbstractSniff
{
    /**
     * Default suffixes that indicate the class is an exception or error.
     */
    private const array DEFAULT_SUFFIXES = [
        'Exception',
        'Error',
        'Throwable',
    ];

    public function getCode(): string
    {
        return 'DocbookCS.ExceptionName';
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, string $content, string $filePath): array
    {
        $violations = [];
        $suffixes = $this->getSuffixes();

        $classnames = $document->getElementsByTagName('classname');

        /** @var \DOMElement $node */
        foreach ($classnames as $node) {
            $text = trim($node->textContent);

            if ($text === '') {
                continue;
            }

            if ($node->parentNode instanceof \DOMElement && $node->parentNode->localName === 'ooclass') {
                continue;
            }

            if ($this->looksLikeException($text, $suffixes)) {
                $violations[] = $this->createViolation(
                    $filePath,
                    $node->getLineNo(),
                    sprintf(
                        '"%s" is wrapped in <classname> but should use <exceptionname>.',
                        $text,
                    ),
                );
            }
        }

        return $violations;
    }

    /**
     * @param list<string> $suffixes
     */
    private function looksLikeException(string $text, array $suffixes): bool
    {
        $parts = explode('\\', $text);
        $baseName = end($parts);

        return array_any(
            $suffixes,
            static fn($suffix) => str_ends_with($baseName, $suffix)
        );
    }

    /** @return list<string> */
    private function getSuffixes(): array
    {
        return self::DEFAULT_SUFFIXES;
    }
}
