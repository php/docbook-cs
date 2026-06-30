<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Report\Severity;

/**
 * Flags second-person pronouns ("you", "your", ...) in documentation prose.
 *
 * The PHP manual style guide asks for an impersonal tone and discourages
 * addressing the reader directly.
 *
 * Text that is not prose is ignored: verbatim/code blocks (such as
 * <programlisting> or <screen>), inline code and value elements (such as
 * <literal>, <varname> or <parameter>, which often carry example input
 * containing those words), and quoted/cited external material (such as
 * <quote> or <citation>, where the manual is not the one using the second
 * person). CDATA sections are skipped as verbatim by nature.
 *
 * Extra words can be flagged via the "additionalPronouns" property. Keep in
 * mind that very short tokens are prone to false positives (e.g. "us" matches
 * "us-east-1"), so this is best combined with running the linter in diff mode.
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
     * Elements whose textual content is not prose and must not be inspected:
     * verbatim/code blocks, inline code/value elements, and quoted material.
     */
    private const array SKIP_ANCESTORS = [
        // verbatim / code blocks
        'programlisting',
        'screen',
        'literallayout',
        'computeroutput',
        'userinput',
        'synopsis',
        'funcsynopsis',
        'classsynopsis',
        'methodsynopsis',
        'cmdsynopsis',
        'fieldsynopsis',
        'constructorsynopsis',
        'destructorsynopsis',
        // inline code / literal values
        'code',
        'literal',
        'constant',
        'varname',
        'parameter',
        'option',
        'envar',
        'uri',
        'filename',
        'function',
        'methodname',
        'classname',
        'exceptionname',
        'interfacename',
        'type',
        'replaceable',
        'command',
        'property',
        'package',
        'prompt',
        // quoted / cited external text
        'quote',
        'blockquote',
        'citation',
        'epigraph',
        'attribution',
        'foreignphrase',
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

        $pattern = '/\b(' . implode('|', array_map('preg_quote', $pronouns)) . ')\b/iu';

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//text()');

        if ($nodes === false) {
            return $violations;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMText || $node instanceof \DOMCdataSection) {
                continue;
            }

            $value = $node->nodeValue ?? '';

            if (trim($value) === '' || $this->insideSkippedAncestor($node)) {
                continue;
            }

            if (preg_match_all($pattern, $value, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            // getLineNo() reports the node's end line; derive its start line so
            // each match can be placed on its real line (matters in diff mode).
            $startLine = $node->getLineNo() - substr_count($value, "\n");

            foreach ($matches[1] as [$word, $offset]) {
                $line = $startLine + substr_count($value, "\n", 0, $offset);

                $violations[] = $this->createViolation(
                    $filePath,
                    $line,
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
