<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

/**
 * Detects list and table elements wrapped directly in a <para>.
 *
 * In the PHP documentation, block elements such as <simplelist>,
 * <variablelist>, <itemizedlist> or <table> belong as a direct child of
 * the containing section, not inside a <para>. A <para> is only flagged
 * when the block is its sole child and the <para> carries no text, so a
 * legitimate intro sentence followed by a <table> stays allowed.
 */
final class ListInParaSniff extends AbstractSniff
{
    /**
     * Block elements that must not be the sole content of a <para>.
     */
    private const array DISALLOWED_IN_PARA = [
        'simplelist',
        'variablelist',
        'itemizedlist',
        'orderedlist',
        'table',
        'informaltable',
    ];

    public function getCode(): string
    {
        return 'DocbookCS.ListInPara';
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, string $content, string $filePath): array
    {
        $violations = [];
        $disallowed = $this->getDisallowedElements();

        // Single XPath pass: select disallowed blocks that are a direct child
        // of a <para>, in document order. local-name() keeps it namespace
        // agnostic. This avoids the nested para/child loop.
        $predicate = implode(' or ', array_map(
            static fn(string $name): string => sprintf("local-name()='%s'", $name),
            $disallowed,
        ));

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query("//*[local-name()='para']/*[$predicate]");

        if ($nodes === false) {
            return $violations;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $para = $node->parentNode;
            if (!$para instanceof \DOMElement || !$this->isSoleBlockContent($para, $node)) {
                continue;
            }

            $name = strtolower($node->localName ?? '');
            $violations[] = $this->createViolation(
                $filePath,
                $node->getLineNo(),
                sprintf(
                    '<%s> must not be wrapped in <para>; place it directly in the containing element.',
                    $name,
                ),
            );
        }

        return $violations;
    }

    /**
     * True when $block is the only element child of $para and $para has no
     * text of its own (whitespace and comments are ignored).
     */
    private function isSoleBlockContent(\DOMElement $para, \DOMElement $block): bool
    {
        foreach ($para->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if (!$child->isSameNode($block)) {
                    return false;
                }

                continue;
            }

            if ($child instanceof \DOMText && trim($child->textContent) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function getDisallowedElements(): array
    {
        $extra = $this->getProperty('additionalListElements');

        if ($extra === '') {
            return self::DISALLOWED_IN_PARA;
        }

        $additional = array_map(static fn(string $s): string => strtolower(trim($s)), explode(',', $extra));
        $additional = array_filter($additional, static fn(string $s): bool => $s !== '');

        return array_values(array_unique(array_merge(self::DISALLOWED_IN_PARA, $additional)));
    }
}
