<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

/**
 * Detects list elements wrapped directly in a <para>.
 *
 * In the PHP documentation, lists such as <simplelist>, <variablelist>
 * and <itemizedlist> must not be nested inside a <para>; they belong as
 * a direct child of the containing section (or list item). Wrapping them
 * in a <para> is a recurring source of build/style breakage.
 */
final class ListInParaSniff extends AbstractSniff
{
    /**
     * List elements that must not appear directly inside a <para>.
     */
    private const array DISALLOWED_IN_PARA = [
        'simplelist',
        'variablelist',
        'itemizedlist',
        'orderedlist',
    ];

    public function getCode(): string
    {
        return 'DocbookCS.ListInPara';
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, string $content, string $filePath): array
    {
        $violations = [];

        foreach ($this->getDisallowedElements() as $tag) {
            foreach ($document->getElementsByTagName($tag) as $list) {
                $parent = $list->parentNode;

                if (
                    $parent instanceof \DOMElement
                    && strtolower($parent->localName ?? '') === 'para'
                ) {
                    $name = strtolower($list->localName ?: $tag);

                    $violations[] = $this->createViolation(
                        $filePath,
                        $list->getLineNo(),
                        sprintf(
                            '<%s> must not be wrapped in <para>; place it directly in the containing element.',
                            $name,
                        ),
                    );
                }
            }
        }

        return $violations;
    }

    /** @return list<string> */
    private function getDisallowedElements(): array
    {
        $extra = $this->getProperty('additionalListElements');

        if ($extra === '') {
            return self::DISALLOWED_IN_PARA;
        }

        $additional = array_map('trim', explode(',', $extra));
        $additional = array_filter($additional, static fn(string $s): bool => $s !== '');

        return array_values(array_unique(array_merge(self::DISALLOWED_IN_PARA, $additional)));
    }
}
