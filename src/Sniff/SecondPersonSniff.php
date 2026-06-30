<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Report\Severity;

/**
 * Flags second-person pronouns ("you", "your", ...) in documentation prose.
 *
 * The PHP manual style guide asks for an impersonal tone and discourages
 * addressing the reader directly. Text inside verbatim/code elements (such
 * as <programlisting> or <screen>) is ignored, since it may legitimately
 * contain those words as part of sample code or output.
 *
 * Extra words can be flagged via the "additionalPronouns" property, e.g.
 * to also catch first-person plural ("we", "us", "our").
 */
final class SecondPersonSniff extends AbstractSniff
{
    /**
     * Default pronouns to flag, longest first so the alternation prefers
     * the most specific match.
     */
    private const array DEFAULT_PRONOUNS = [
        'yourselves',
        'yourself',
        'yours',
        'your',
        'you',
    ];

    /**
     * Elements whose textual content is verbatim (code, output, synopsis)
     * and must not be inspected for prose.
     */
    private const array SKIP_ANCESTORS = [
        'programlisting',
        'screen',
        'literallayout',
        'computeroutput',
        'userinput',
        'code',
        'synopsis',
        'funcsynopsis',
        'classsynopsis',
        'methodsynopsis',
        'cmdsynopsis',
        'fieldsynopsis',
        'constructorsynopsis',
        'destructorsynopsis',
    ];

    public function getCode(): string
    {
        return 'DocbookCS.SecondPerson';
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, string $content, string $filePath): array
    {
        $violations = [];
        $pronouns = $this->getPronouns();

        $pattern = '/\b(' . implode('|', array_map('preg_quote', $pronouns)) . ')\b/i';

        $xpath = new \DOMXPath($document);

        /** @var \DOMText $node */
        foreach ($xpath->query('//text()') as $node) {
            if ($this->insideSkippedAncestor($node)) {
                continue;
            }

            if (preg_match_all($pattern, $node->nodeValue ?? '', $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $word) {
                $violations[] = $this->createViolation(
                    $filePath,
                    $node->getLineNo(),
                    sprintf(
                        'Second-person "%s" addresses the reader directly; use an impersonal tone.',
                        $word,
                    ),
                    Severity::WARNING,
                );
            }
        }

        return $violations;
    }

    private function insideSkippedAncestor(\DOMNode $node): bool
    {
        for ($parent = $node->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
            if (in_array(strtolower($parent->localName ?? ''), self::SKIP_ANCESTORS, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function getPronouns(): array
    {
        $extra = $this->getProperty('additionalPronouns');

        if ($extra === '') {
            return self::DEFAULT_PRONOUNS;
        }

        $additional = array_filter(
            array_map('trim', explode(',', $extra)),
            static fn(string $s): bool => $s !== '',
        );

        return array_values(array_unique([...self::DEFAULT_PRONOUNS, ...$additional]));
    }
}
