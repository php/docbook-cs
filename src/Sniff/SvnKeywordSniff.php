<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

/**
 * Flags dead SVN keyword markers (e.g. <!-- $Revision$ -->) left over from the
 * pre-git era. Subversion expanded these via svn:keywords on every commit; since
 * the manual moved to git they are never updated and carry no information.
 */
final class SvnKeywordSniff extends AbstractSniff
{
    /** Standard svn:keywords names, all inert under git. */
    private const array SVN_KEYWORDS = [
        'Revision',
        'Id',
        'Date',
        'Author',
        'Header',
        'Source',
        'HeadURL',
        'LastChangedDate',
        'LastChangedRevision',
        'LastChangedBy',
    ];

    public function getCode(): string
    {
        return 'DocbookCS.SvnKeyword';
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, string $content, string $filePath): array
    {
        $pattern = $this->buildPattern();
        $anchorLine = $this->anchorLine($document);

        $violations = [];

        $xpath = new \DOMXPath($document);
        $comments = $xpath->query('//comment()');

        if ($comments === false) {
            return [];
        }

        /** @var \DOMComment $comment */
        foreach ($comments as $comment) {
            if (preg_match($pattern, $comment->data, $match) !== 1) {
                continue;
            }

            $violations[] = $this->createViolation(
                $filePath,
                // Anchored to the root element (not the comment line) so the
                // violation stays relevant in diff mode: any change inside the
                // document surfaces the dead marker, matching the "remove it
                // when you touch the file" convention. The real marker line is
                // carried in the message so the report stays actionable in
                // full-scan mode too.
                $anchorLine,
                sprintf(
                    'Dead SVN keyword marker "%s" on line %d; remove it (no longer expanded under git).',
                    trim($match[0]),
                    $comment->getLineNo(),
                ),
            );
        }

        return $violations;
    }

    private function anchorLine(\DOMDocument $document): int
    {
        return $document->documentElement?->getLineNo() ?? 1;
    }

    private function buildPattern(): string
    {
        $names = self::SVN_KEYWORDS;

        $extra = $this->getProperty('additionalKeywords');
        if ($extra !== '') {
            $additional = array_filter(
                array_map('trim', explode(',', $extra)),
                static fn(string $s): bool => $s !== '',
            );
            $names = array_values(array_unique([...$names, ...$additional]));
        }

        $alternation = implode('|', array_map(
            static fn(string $name): string => preg_quote($name, '/'),
            $names,
        ));

        // Matches "$Revision$" and the expanded "$Revision: 12345 $" form.
        return '/\$(?:' . $alternation . ')\b[^$]*\$/';
    }
}
